<?php
declare(strict_types=1);

namespace App\Respond;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;

final class RespondController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private InviteRepo $invites,
        private ResponseRepo $responses,
        private UserRepo $users,
        private ABAssigner $assigner,
        private AbEventRepo $events,
        private Clock $clock,
        private LinkResolver $maps,
        private Postman $postman,
    ) {}

    public function open(string $token): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'Not found', 'theme' => 'bubblegum',
                'reason' => 'This invite could not be found.',
            ]), 404);
        }

        if ($this->isUnavailable($invite)) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'No longer available', 'theme' => $invite['theme_key'] ?: 'bubblegum',
                'reason' => 'This invite is no longer available.',
            ]));
        }

        $theme = $this->assigner->assignTo($invite);

        if ($invite['status'] === InviteState::SENT) {
            $this->invites->updateStatus((int) $invite['id'], InviteState::OPENED);
            $this->events->log((int) $invite['id'], $theme, 'opened');
        }

        return Response::html($this->view->render('respond/show', [
            'title'       => 'You have an invite',
            'theme'       => $theme,
            'csrf'        => $this->csrf->token(),
            'token'       => $invite['public_token'],
            'senderLabel' => $this->senderLabel($invite),
            'message'     => $invite['message'],
            'dateMode'    => $invite['date_mode'],
            'options'     => $this->invites->dateOptions((int) $invite['id']),
            'meals'       => MealOptions::CHOICES,
        ]));
    }

    public function submit(string $token, array $input, string $csrf): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'Not found', 'theme' => 'bubblegum',
                'reason' => 'This invite could not be found.',
            ]), 404);
        }
        if ($this->isUnavailable($invite)) {
            return Response::html($this->view->render('respond/closed', [
                'title' => 'No longer available', 'theme' => $invite['theme_key'] ?: 'bubblegum',
                'reason' => 'This invite is no longer available.',
            ]));
        }

        $theme = $this->assigner->assignTo($invite);

        $alreadyAnswered = [
            InviteState::RESPONDED,
            InviteState::PENDING_SENDER,
            InviteState::CONFIRMED,
            InviteState::DECLINED,
        ];
        if (in_array($invite['status'], $alreadyAnswered, true)) {
            $existing = $this->responses->findByInvite((int) $invite['id']);
            $when = null;
            if ($existing !== null) {
                try {
                    $when = (new \DateTimeImmutable($existing['chosen_start']))->format('D, M j \a\t g:i A');
                } catch (\Exception) {
                    $when = $existing['chosen_start'];
                }
            }
            return Response::html($this->view->render('respond/confirmed', [
                'title'       => 'Your answer is in',
                'theme'       => $theme,
                'dateMode'    => $invite['date_mode'],
                'reveal'      => $this->revealLabel($invite),
                'wasAnonymous' => (int) $invite['is_anonymous'] === 1,
                'when'        => $when ?? '—',
            ]));
        }

        if (!$this->csrf->validate($csrf)) {
            return $this->reshow($invite, $theme, 'Your session expired. Please try again.', 400);
        }

        $start = $this->parseDate((string) ($input['chosen_start'] ?? ''));
        if ($start === null) {
            return $this->reshow($invite, $theme, 'Please pick a day and time.', 422);
        }
        $end = $start->modify('+2 hours');

        $meal = (string) ($input['meal_choice'] ?? '');
        $meal = MealOptions::isValid($meal) ? $meal : null;

        $pickupRaw = $this->clean($input['pickup_raw'] ?? null);
        $pickup = $this->maps->resolve((string) ($pickupRaw ?? ''));

        $this->responses->store((int) $invite['id'], [
            'chosen_start'     => $start->format('Y-m-d H:i:s'),
            'chosen_end'       => $end->format('Y-m-d H:i:s'),
            'meal_choice'      => $meal,
            'meal_wish'        => $this->clean($input['meal_wish'] ?? null),
            'crush_contact'    => $this->clean($input['crush_contact'] ?? null),
            'pickup_raw'       => $pickupRaw,
            'pickup_name'      => $pickup['name'],
            'pickup_address'   => $pickup['address'],
            'pickup_clean_url' => $pickup['clean_url'],
        ]);

        $final = $invite['date_mode'] === 'confirm' ? InviteState::PENDING_SENDER : InviteState::CONFIRMED;
        $this->invites->updateStatus((int) $invite['id'], InviteState::RESPONDED);
        $this->invites->updateStatus((int) $invite['id'], $final);
        $this->events->log((int) $invite['id'], $theme, 'completed');

        $sender = $this->users->findById((int) $invite['sender_id']);
        if ($sender !== null) {
            $stored = $this->responses->findByInvite((int) $invite['id']);
            if ($stored !== null) {
                $this->postman->sendResult($invite, $stored, $sender);
            }
        }

        return Response::html($this->view->render('respond/confirmed', [
            'title'       => 'Your answer is in',
            'theme'       => $theme,
            'dateMode'    => $invite['date_mode'],
            'reveal'      => $this->revealLabel($invite),
            'wasAnonymous' => (int) $invite['is_anonymous'] === 1,
            'when'        => $start->format('D, M j \a\t g:i A'),
        ]));
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function clean(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function revealLabel(array $invite): ?string
    {
        $anon = (int) $invite['is_anonymous'] === 1;
        $reveal = (int) $invite['reveal_on_response'] === 1;
        if ($anon && !$reveal) {
            return null;
        }
        $sender = $this->users->findById((int) $invite['sender_id']);
        return $sender['name'] ?? $sender['email'] ?? null;
    }

    private function reshow(array $invite, string $theme, string $error, int $status): Response
    {
        return Response::html($this->view->render('respond/show', [
            'title' => 'You have an invite', 'theme' => $theme,
            'csrf' => $this->csrf->token(), 'token' => $invite['public_token'],
            'senderLabel' => $this->senderLabel($invite), 'message' => $invite['message'],
            'dateMode' => $invite['date_mode'],
            'options' => $this->invites->dateOptions((int) $invite['id']),
            'meals' => MealOptions::CHOICES, 'error' => $error,
        ]), $status);
    }

    private function isUnavailable(array $invite): bool
    {
        $terminal = [InviteState::CLOSED, InviteState::EXPIRED, InviteState::BLOCKED];
        if (in_array($invite['status'], $terminal, true)) {
            return true;
        }
        return $invite['expires_at'] < $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function senderLabel(array $invite): string
    {
        if ((int) $invite['is_anonymous'] === 1) {
            return 'a secret admirer';
        }
        $sender = $this->users->findById((int) $invite['sender_id']);
        return $sender['name'] ?? 'someone';
    }
}

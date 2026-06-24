<?php
declare(strict_types=1);

namespace App\Invite;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\MealOptions;
use App\Security\BlockRepo;
use App\Security\RateLimiter;

final class InviteController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private InviteRepo $invites,
        private UserRepo $users,
        private Clock $clock,
        private string $appUrl,
        private Postman $postman,
        private RateLimiter $limits,
        private BlockRepo $blocks,
        private InvitePlaceRepo $places,
        private LinkResolver $maps,
    ) {}

    public function dashboard(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return Response::html($this->view->render('invite/dashboard', [
            'title'   => 'Your invites',
            'invites' => $this->invites->listBySender($userId),
            'appUrl'  => rtrim($this->appUrl, '/'),
        ]));
    }

    public function showNew(?int $userId): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        return $this->renderForm(null, [], 200, $this->users->findById($userId));
    }

    public function create(?int $userId, array $input, string $csrf): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->renderForm('Your session expired. Please try again.', $input, 400);
        }

        $email = trim((string) ($input['crush_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderForm('Please enter a valid email for your crush.', $input, 422);
        }

        $dateMode = ($input['date_mode'] ?? 'instant') === 'confirm' ? 'confirm' : 'instant';

        $sender = $this->users->findById($userId);

        // Check the tighter per-email cap first, then the per-sender cap, with
        // short-circuit AND so a blocked email never burns the sender's own quota.
        if (!$this->limits->hit('invites_per_email', strtolower($email), 3, 86400)
            || !$this->limits->hit('invites_per_sender', (string) $userId, 20, 86400)) {
            return $this->renderForm('You have sent too many invites for now. Please try again later.', $input, 429);
        }

        if ($this->blocks->isBlocked($userId, $email)) {
            return $this->renderForm('This person has asked not to receive invites.', $input, 403);
        }

        $invite = $this->invites->create([
            'sender_id'          => $userId,
            'crush_email'        => $email,
            'crush_name'         => trim((string) ($input['crush_name'] ?? '')) ?: null,
            'is_anonymous'       => !empty($input['is_anonymous']),
            'reveal_on_response' => !empty($input['reveal_on_response']),
            'date_mode'          => $dateMode,
            'message'            => trim((string) ($input['message'] ?? '')) ?: null,
            'lang'               => $sender['lang'] ?? null,
            'expires_at'         => $this->clock->now()->modify('+30 days')->format('Y-m-d H:i:s'),
        ]);

        // Proposed slots (confirm mode): start/end pairs.
        $starts = (array) ($input['slot_start'] ?? []);
        $ends   = (array) ($input['slot_end'] ?? []);
        foreach ($starts as $i => $start) {
            $start = trim((string) $start);
            $end   = trim((string) ($ends[$i] ?? ''));
            if ($start !== '' && $end !== '') {
                $this->invites->addDateOption($invite['id'], $start, $end);
            }
        }

        $placeInput = (array) ($input['places'] ?? []);
        foreach (MealOptions::CHOICES as $meal) {
            $key = $meal['key'];
            $name = trim((string) ($placeInput[$key]['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $url = trim((string) ($placeInput[$key]['url'] ?? ''));
            $cuisine = trim((string) ($placeInput[$key]['cuisine'] ?? '')) ?: null;
            $resolved = $url !== '' ? $this->maps->resolve($url) : ['name' => null, 'address' => null, 'clean_url' => null];
            $this->places->add(
                (int) $invite['id'], $key, $name, $url !== '' ? $url : null,
                $resolved['name'], $resolved['address'], $resolved['clean_url'], $cuisine
            );
        }

        $this->postman->sendInvite($invite);

        return $this->redirect('/i/' . $invite['public_token'] . '/created');
    }

    public function showCreated(?int $userId, string $token): Response
    {
        if ($userId === null) {
            return $this->redirect('/login');
        }
        $invite = $this->invites->findByToken($token);
        if ($invite === null || $invite['sender_id'] !== $userId) {
            return Response::html($this->view->render('invite/dashboard', [
                'title'   => 'Your invites',
                'invites' => $this->invites->listBySender($userId),
                'appUrl'  => rtrim($this->appUrl, '/'),
            ]), 404);
        }
        return Response::html($this->view->render('invite/created', [
            'title'  => 'Invite ready',
            'link'   => rtrim($this->appUrl, '/') . '/i/' . $invite['public_token'],
            'invite' => $invite,
        ]));
    }

    private function renderForm(?string $error = null, array $old = [], int $status = 200, ?array $me = null): Response
    {
        return Response::html($this->view->render('invite/new', [
            'title' => 'New invite',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'old'   => $old,
            'meals' => MealOptions::CHOICES,
            'me'    => $me,
        ]), $status);
    }

    private function redirect(string $to): Response
    {
        return (new Response('', 302))->withHeader('Location', $to);
    }
}

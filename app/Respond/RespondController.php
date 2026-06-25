<?php
declare(strict_types=1);

namespace App\Respond;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Respond\CrushOnboarder;

final class RespondController
{
    private const THEME_TEMPLATES = ['love-letter', 'bubblegum', 'midnight'];

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
        private CrushOnboarder $onboarder,
        private InvitePlaceRepo $places,
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

        return $this->renderInvite($invite, $theme);
    }

    public function submit(string $token, array $input, string $csrf, string $acceptLanguage = ''): Response
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

        $rawStart = trim((string) ($input['chosen_start'] ?? ''));
        if ($rawStart === '') {
            $d = trim((string) ($input['chosen_date'] ?? ''));
            $t = trim((string) ($input['chosen_time'] ?? ''));
            $rawStart = ($d !== '' && $t !== '') ? $d . ' ' . $t : '';
        }
        $start = $this->parseDate($rawStart);
        if ($start === null) {
            return $this->reshow($invite, $theme, 'Please pick a day and time.', 422);
        }
        $end = $start->modify('+2 hours');

        $meal = (string) ($input['meal_choice'] ?? '');
        $meal = MealOptions::isValid($meal) ? $meal : null;

        $chosenPlaceId = null;
        $cp = (int) ($input['chosen_place'] ?? 0);
        if ($cp > 0) {
            foreach ($this->places->groupedForInvite((int) $invite['id']) as $rows) {
                foreach ($rows as $row) {
                    if ((int) $row['id'] === $cp) { $chosenPlaceId = $cp; break 2; }
                }
            }
        }

        $pickupRaw = $this->clean($input['pickup_raw'] ?? null);
        $pickup = $this->maps->resolve((string) ($pickupRaw ?? ''));
        if ($pickupRaw === null && $meal !== null) {
            $place = $this->places->forMeal((int) $invite['id'], $meal);
            if ($place !== null) {
                $pickup = [
                    'name'      => $place['place_resolved_name'] ?: $place['place_name'],
                    'address'   => $place['place_resolved_address'],
                    'clean_url' => $place['place_clean_url'] ?: $place['place_url'],
                ];
            }
        }

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
            'chosen_place_id'  => $chosenPlaceId,
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

        try {
            $this->onboarder->onboard((string) $invite['crush_email'], $invite['crush_name'], \App\Core\Locale::detect($acceptLanguage));
        } catch (\Throwable $e) {
            error_log('Crush onboarding failed: ' . $e->getMessage());
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
        return $this->renderInvite($invite, $theme, $error, $status);
    }

    private function renderInvite(array $invite, string $theme, ?string $error = null, int $status = 200): Response
    {
        $key = in_array($theme, self::THEME_TEMPLATES, true) ? $theme : 'bubblegum';

        $grouped = $this->places->groupedForInvite((int) $invite['id']);
        $curatedKeys = array_values(array_filter(
            array_map(static fn(array $m) => $m['key'], MealOptions::CHOICES),
            static fn(string $k) => isset($grouped[$k])
        ));
        $focusVibe = null;
        $focusOptions = [];
        $collapseMeal = null;
        $visibleMeals = MealOptions::CHOICES;
        if (count($curatedKeys) === 1) {
            $only = $curatedKeys[0];
            if (count($grouped[$only]) >= 2) {
                $focusVibe = $this->mealByKey($only);
                $focusOptions = $grouped[$only];
            } else {
                $collapseMeal = $this->mealByKey($only);
            }
            $visibleMeals = [$this->mealByKey($only)];
        } elseif (count($curatedKeys) >= 2) {
            $visibleMeals = array_values(array_filter(MealOptions::CHOICES, static fn(array $m) => isset($grouped[$m['key']])));
        }
        // first-option-per-vibe map for the existing chip reveal:
        $places = [];
        foreach ($grouped as $k => $rows) {
            $places[$k] = $rows[0];
        }

        return Response::html($this->view->render('respond/themes/' . $key, [
            'title'        => 'You have an invite',
            'theme'        => $key,
            'csrf'         => $this->csrf->token(),
            'token'        => $invite['public_token'],
            'senderLabel'  => $this->senderLabel($invite),
            'message'      => $invite['message'],
            'meals'        => $visibleMeals,
            'places'       => $places,
            'collapseMeal' => $collapseMeal,
            'focusVibe'    => $focusVibe,
            'focusOptions' => $focusOptions,
            'error'        => $error,
        ]), $status);
    }

    private function mealByKey(string $k): array
    {
        foreach (MealOptions::CHOICES as $m) {
            if ($m['key'] === $k) {
                return $m;
            }
        }
        return ['key' => $k, 'label' => ucfirst($k), 'icon' => 'ic-utensils'];
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

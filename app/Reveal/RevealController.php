<?php
declare(strict_types=1);

namespace App\Reveal;

use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Profile\Avatars;

final class RevealController
{
    public function __construct(
        private View $view,
        private UserRepo $users,
        private InviteRepo $invites,
        private ResponseRepo $responses,
        private IcsBuilder $ics,
        private InvitePlaceRepo $places,
        private Csrf $csrf,
    ) {}

    public function show(?int $userId, string $token): Response
    {
        $ctx = $this->load($userId, $token);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$invite, $response, $complete] = $ctx;

        if ($response === null) {
            return $this->render('waiting', $invite, null);
        }
        if (!$complete) {
            return $this->render('locked', $invite, null);
        }
        return $this->render('reveal', $invite, $response);
    }

    public function downloadIcs(?int $userId, string $token): Response
    {
        $ctx = $this->load($userId, $token);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$invite, $response, $complete] = $ctx;

        if ($response === null) {
            return Response::html('<h1>Not found</h1>', 404);
        }
        if (!$complete) {
            return (new Response('', 302))->withHeader('Location', '/invites/' . $invite['public_token'] . '/response');
        }

        $crush = $invite['crush_name'] ?: 'your crush';
        $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crush,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $place !== '' ? $place : null,
            'description' => !empty($response['meal_choice']) ? (string) $response['meal_choice'] : null,
        ]);

        return new Response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Date.ics"',
        ]);
    }

    /**
     * @return Response|array{0:array,1:?array,2:bool} early Response, or [invite, response, profileComplete]
     */
    private function load(?int $userId, string $token): Response|array
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        $invite = $this->invites->findByToken($token);
        if ($invite === null || $invite['sender_id'] !== $userId) {
            return Response::html($this->view->render('reveal/response', [
                'title' => 'Not found', 'state' => 'missing', 'invite' => null, 'response' => null,
            ]), 404);
        }
        $sender = $this->users->findById($userId);
        return [$invite, $this->responses->findByInvite((int) $invite['id']), UserRepo::isProfileComplete($sender ?? [])];
    }

    private function render(string $state, array $invite, ?array $response): Response
    {
        $chosenPlace = null;
        if ($response !== null && !empty($response['chosen_place_id'])) {
            $chosenPlace = $this->places->findById((int) $response['chosen_place_id']);
        }
        $sender = $this->users->findById((int) ($invite['sender_id'] ?? 0));
        return Response::html($this->view->render('reveal/response', [
            'title'       => 'Your crush',
            'state'       => $state,
            'invite'      => $invite,
            'response'    => $response,
            'chosenPlace' => $chosenPlace,
            'user'        => $sender,
            'avatars'     => Avatars::keys(),
            'csrf'        => $this->csrf->token(),
            'returnTo'    => '/invites/' . ($invite['public_token'] ?? '') . '/response',
        ]));
    }
}

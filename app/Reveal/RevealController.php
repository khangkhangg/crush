<?php
declare(strict_types=1);

namespace App\Reveal;

use App\Auth\UserRepo;
use App\Core\Response;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;

final class RevealController
{
    public function __construct(
        private View $view,
        private UserRepo $users,
        private InviteRepo $invites,
        private ResponseRepo $responses,
        private IcsBuilder $ics,
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
        return Response::html($this->view->render('reveal/response', [
            'title'    => 'Your crush',
            'state'    => $state,
            'invite'   => $invite,
            'response' => $response,
        ]));
    }
}

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

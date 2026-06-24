<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Security\BlockRepo;

final class BlockController
{
    public function __construct(
        private View $view,
        private InviteRepo $invites,
        private BlockRepo $blocks,
    ) {}

    public function report(string $token): Response
    {
        $invite = $this->invites->findByToken($token);
        if ($invite === null) {
            return Response::html($this->view->render('respond/blocked', [
                'title' => 'Not found', 'known' => false,
            ]), 404);
        }
        $this->blocks->block((int) $invite['sender_id'], (string) $invite['crush_email'], 'reported');
        $this->invites->updateStatus((int) $invite['id'], InviteState::BLOCKED);

        return Response::html($this->view->render('respond/blocked', [
            'title' => 'Done', 'known' => true,
        ]));
    }
}

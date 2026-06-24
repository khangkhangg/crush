<?php
declare(strict_types=1);

namespace App\Invite;

use App\Auth\UserRepo;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class InviteController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private InviteRepo $invites,
        private UserRepo $users,
        private Clock $clock,
        private string $appUrl,
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
        return $this->renderForm();
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

        $invite = $this->invites->create([
            'sender_id'          => $userId,
            'crush_email'        => $email,
            'crush_name'         => trim((string) ($input['crush_name'] ?? '')) ?: null,
            'is_anonymous'       => !empty($input['is_anonymous']),
            'reveal_on_response' => !empty($input['reveal_on_response']),
            'date_mode'          => $dateMode,
            'message'            => trim((string) ($input['message'] ?? '')) ?: null,
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

    private function renderForm(?string $error = null, array $old = [], int $status = 200): Response
    {
        return Response::html($this->view->render('invite/new', [
            'title' => 'New invite',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'old'   => $old,
        ]), $status);
    }

    private function redirect(string $to): Response
    {
        return (new Response('', 302))->withHeader('Location', $to);
    }
}

<?php
declare(strict_types=1);

namespace App\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Mail\Email;
use App\Mail\Mailer;

final class LandingController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private MagicLink $magic,
        private Session $session,
        private Mailer $mailer,
        private string $appUrl,
    ) {}

    public function home(?int $userId): Response
    {
        if ($userId !== null) {
            return (new Response('', 302))->withHeader('Location', '/invites');
        }
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
        ]));
    }

    public function start(array $input, string $csrf): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->render('Your session expired. Please try again.', 400);
        }

        $name  = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('Please enter your name and a valid email.', 422, $name, $email);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $this->sendLink($email, $this->magic->start($email));
            return $this->render(null, 200, $name, $email, sent: $email);
        }

        $user = $this->users->create($email, $name, 'magic');
        $this->sendLink($email, $this->magic->start($email));
        $this->session->login((int) $user['id']);
        return (new Response('', 302))->withHeader('Location', '/invites/new');
    }

    private function sendLink(string $email, string $token): void
    {
        $link = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        $safe = htmlspecialchars($link, ENT_QUOTES);
        $html = '<p style="font-family:sans-serif">Tap to sign in to Crush:</p>'
              . '<p><a href="' . $safe . '">Sign in</a></p>'
              . '<p style="color:#999;font-size:12px">Or paste: ' . $safe . '</p>';
        try {
            $this->mailer->send(new Email($email, 'Your Crush sign-in link', $html));
        } catch (\Throwable $e) {
            error_log('Crush landing mail failed: ' . $e->getMessage());
        }
    }

    private function render(?string $error, int $status, string $name = '', string $email = '', ?string $sent = null): Response
    {
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'name'  => $name,
            'email' => $email,
            'sent'  => $sent,
        ]), $status);
    }
}

<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class AuthController
{
    public function __construct(
        private View $view,
        private Session $session,
        private Csrf $csrf,
        private MagicLink $magic,
        private string $magicLinkSink,   // file path: where the dev link is written until email is wired
        private string $appUrl,
    ) {}

    public function showLogin(): Response
    {
        return Response::html($this->view->render('auth/login', [
            'csrf'  => $this->csrf->token(),
            'title' => 'Sign in',
        ]));
    }

    public function startMagic(string $email, string $csrf): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return Response::html($this->view->render('auth/login', [
                'csrf'  => $this->csrf->token(),
                'title' => 'Sign in',
                'error' => 'Your session expired. Please try again.',
            ]), 400);
        }

        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::html($this->view->render('auth/login', [
                'csrf'  => $this->csrf->token(),
                'title' => 'Sign in',
                'error' => 'Please enter a valid email address.',
            ]), 422);
        }

        $token = $this->magic->start($email);
        $link  = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        // Until Plan 6 wires the mailer, persist the link so dev can use it.
        @file_put_contents($this->magicLinkSink, $link . "\n", FILE_APPEND);

        return Response::html($this->view->render('auth/login', [
            'csrf'  => $this->csrf->token(),
            'title' => 'Check your email',
            'sent'  => $email,
        ]));
    }

    public function completeMagic(string $token): Response
    {
        $user = $this->magic->complete($token);
        if ($user === null) {
            return Response::html($this->view->render('auth/login', [
                'csrf'  => $this->csrf->token(),
                'title' => 'Sign in',
                'error' => 'That sign-in link is invalid or has expired.',
            ]), 410);
        }
        $this->session->login($user['id']);
        return (new Response('', 302))->withHeader('Location', '/');
    }

    public function logout(string $csrf): Response
    {
        if ($this->csrf->validate($csrf)) {
            $this->session->logout();
        }
        return (new Response('', 302))->withHeader('Location', '/');
    }
}

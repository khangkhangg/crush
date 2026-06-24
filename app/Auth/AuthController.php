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
        private \App\Mail\Mailer $mailer,
        private string $appUrl,
    ) {}

    public function showLogin(?string $errorCode = null): Response
    {
        $errors = [
            'google' => 'Google sign-in is not set up yet. Try a magic link instead.',
            'oauth'  => 'Google sign-in could not be completed. Please try again.',
        ];
        return Response::html($this->view->render('auth/login', [
            'csrf'  => $this->csrf->token(),
            'title' => 'Sign in',
            'error' => $errors[$errorCode] ?? null,
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
        $html = '<p style="font-family:sans-serif">Tap to sign in to Crush:</p>'
              . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">Sign in</a></p>'
              . '<p style="color:#999;font-size:12px">Or paste: ' . htmlspecialchars($link, ENT_QUOTES) . '</p>';
        try {
            $this->mailer->send(new \App\Mail\Email($email, 'Your Crush sign-in link', $html));
        } catch (\Throwable $e) {
            error_log('Crush magic-link mail failed: ' . $e->getMessage());
        }

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

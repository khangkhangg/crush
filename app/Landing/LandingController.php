<?php
declare(strict_types=1);

namespace App\Landing;

use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Locale;
use App\Core\Response;
use App\Core\View;
use App\Mail\Postman;

final class LandingController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private MagicLink $magic,
        private Session $session,
        private Postman $postman,
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

    public function start(array $input, string $csrf, string $acceptLanguage = ''): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->render('Your session expired. Please try again.', 400);
        }
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->render('Please enter your name and a valid email.', 422, $name, $email);
        }
        if (strlen($password) < 6) {
            return $this->render('Pick a password with at least 6 characters.', 422, $name, $email);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            $hash = (string) ($existing['password_hash'] ?? '');
            if ($hash !== '' && !password_verify($password, $hash)) {
                return $this->render('That email is taken. If it is yours, enter the right password — or use a different email.', 401, $name, $email);
            }
            if ($hash === '') {
                $this->users->setPasswordHash((int) $existing['id'], password_hash($password, PASSWORD_DEFAULT));
            }
            $this->session->login((int) $existing['id']);
            return (new Response('', 302))->withHeader('Location', '/invites/new');
        }

        $lang = Locale::detect($acceptLanguage);
        $user = $this->users->create($email, $name, 'password');
        $this->users->setPasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->users->setLang((int) $user['id'], $lang);
        $this->session->login((int) $user['id']);
        $token = $this->magic->start($email);
        $this->postman->sendWelcome($email, $name, rtrim($this->appUrl, '/') . '/auth/magic/' . $token, $lang);

        return (new Response('', 302))->withHeader('Location', '/invites/new');
    }

    public function switchAccount(): Response
    {
        $this->session->logout();
        return (new Response('', 302))->withHeader('Location', '/');
    }

    private function render(?string $error, int $status, string $name = '', string $email = ''): Response
    {
        return Response::html($this->view->render('landing/home', [
            'title' => 'Crush',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
            'name'  => $name,
            'email' => $email,
        ]), $status);
    }
}

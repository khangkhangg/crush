<?php
declare(strict_types=1);

namespace App\Admin;

use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Security\RateLimiter;

final class AdminAuthController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private Session $session,
        private RateLimiter $limits,
    ) {}

    public function showLogin(?string $error = null, int $status = 200): Response
    {
        return Response::html($this->view->render('admin/login', [
            'title' => 'Admin sign in',
            'csrf'  => $this->csrf->token(),
            'error' => $error,
        ]), $status);
    }

    public function login(array $input, string $csrf, string $ip): Response
    {
        if (!$this->csrf->validate($csrf)) {
            return $this->showLogin('Your session expired. Please try again.', 400);
        }

        $email    = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $okIp    = $this->limits->hit('admin_login_ip', $ip, 10, 3600);
        $okEmail = $this->limits->hit('admin_login_email', strtolower($email), 5, 900);
        if (!$okIp || !$okEmail) {
            return $this->showLogin('Too many attempts. Please wait and try again.', 429);
        }

        $user = $email !== '' ? $this->users->findByEmail($email) : null;
        $hash = $user['password_hash'] ?? null;

        if ($user !== null && is_string($hash) && (int) $user['is_admin'] === 1 && password_verify($password, $hash)) {
            $this->session->login((int) $user['id']);
            return (new Response('', 302))->withHeader('Location', '/admin');
        }

        return $this->showLogin('Invalid email or password.', 401);
    }
}

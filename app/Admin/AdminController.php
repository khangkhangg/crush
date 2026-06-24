<?php
declare(strict_types=1);

namespace App\Admin;

use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Mail\Email;
use App\Mail\MailerFactory;
use App\Security\BlockRepo;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ThemeRepo;

final class AdminController
{
    private const SETTING_KEYS = [
        'mail_driver', 'from_email', 'from_name',
        'resend_api_key', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
        'google_client_id', 'google_client_secret', 'google_redirect_uri',
        'invite_expiry_days',
    ];

    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
        private SettingsRepo $settings,
        private ThemeRepo $themes,
        private AbEventRepo $events,
        private InviteRepo $invites,
        private BlockRepo $blocks,
        private string $appUrl,
    ) {}

    public function dashboard(?int $userId): Response
    {
        if (($admin = $this->requireAdmin($userId)) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/dashboard', [
            'title' => 'Admin', 'blocks' => count($this->blocks->recent()),
            'driver' => $this->settings->get('mail_driver', 'php'),
        ]);
    }

    public function settings(?int $userId, ?string $flash = null): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        return $this->render('admin/settings', [
            'title' => 'Settings', 'csrf' => $this->csrf->token(),
            'values' => $this->settings->all(), 'flash' => $flash, 'keys' => self::SETTING_KEYS,
        ]);
    }

    public function saveSettings(?int $userId, array $input, string $csrf): Response
    {
        if ($this->requireAdmin($userId) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->settings($userId, 'Session expired, please retry.')->withStatus(400);
        }
        foreach (self::SETTING_KEYS as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $this->settings->set($key, trim($input[$key]));
            }
        }
        return (new Response('', 302))->withHeader('Location', '/admin/settings');
    }

    public function sendTest(?int $userId, string $csrf): Response
    {
        if (($admin = $this->requireAdmin($userId)) === null) {
            return $this->forbidden();
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->settings($userId, 'Session expired, please retry.')->withStatus(400);
        }
        try {
            MailerFactory::make($this->settings)->send(new Email(
                (string) $admin['email'],
                'Crush test email',
                '<p>This is a Crush test email. Your mail settings work.</p>'
            ));
            $flash = 'Test email sent to ' . $admin['email'] . '.';
        } catch (\Throwable $e) {
            $flash = 'Test failed: ' . $e->getMessage();
        }
        return $this->settings($userId, $flash);
    }

    private function requireAdmin(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }
        $user = $this->users->findById($userId);
        return ($user !== null && (int) $user['is_admin'] === 1) ? $user : null;
    }

    private function forbidden(): Response
    {
        return Response::html($this->view->render('admin/dashboard', [
            'title' => 'Forbidden', 'forbidden' => true,
        ]), 403);
    }

    private function render(string $tpl, array $data): Response
    {
        return Response::html($this->view->render($tpl, $data));
    }
}

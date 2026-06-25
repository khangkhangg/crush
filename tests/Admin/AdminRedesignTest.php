<?php
declare(strict_types=1);

namespace Tests\Admin;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AdminRedesignTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_admin_dashboard_uses_dark_configuration_shell(): void
    {
        $html = $this->view()->render('admin/dashboard', [
            'title' => 'Admin',
            'driver' => 'smtp',
            'blocks' => 3,
            'adminUser' => ['id' => 7, 'name' => 'Boss', 'email' => 'boss@example.test'],
            'adminCsrf' => 'csrf-token',
        ]);

        $this->assertStringContainsString('data-admin-shell', $html);
        $this->assertStringContainsString('data-admin-page="dashboard"', $html);
        $this->assertStringContainsString('Crush Admin', $html);
        $this->assertStringContainsString('Configuration hub', $html);
        $this->assertStringContainsString('admin-stat-grid', $html);
        $this->assertStringContainsString('data-admin-account', $html);
        $this->assertStringContainsString('Edit profile info', $html);
        $this->assertStringContainsString('Reset password', $html);
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('action="/logout"', $html);
    }

    public function test_admin_settings_page_groups_configuration_sections(): void
    {
        $html = $this->view()->render('admin/settings', [
            'title' => 'Settings',
            'csrf' => 'x',
            'values' => ['mail_driver' => 'smtp'],
            'keys' => ['mail_driver', 'from_email', 'google_client_id', 'invite_expiry_days'],
        ]);

        $this->assertStringContainsString('data-admin-page="settings"', $html);
        $this->assertStringContainsString('App configuration', $html);
        $this->assertStringContainsString('Mail delivery', $html);
        $this->assertStringContainsString('Sign-in providers', $html);
        $this->assertStringContainsString('Operations', $html);
    }

    public function test_admin_login_uses_same_dark_brand_language(): void
    {
        $html = $this->view()->render('admin/login', ['title' => 'Admin sign in', 'csrf' => 'x']);

        $this->assertStringContainsString('data-admin-login', $html);
        $this->assertStringContainsString('Crush Admin', $html);
        $this->assertStringContainsString('Didudi-style operations console', $html);
    }
}

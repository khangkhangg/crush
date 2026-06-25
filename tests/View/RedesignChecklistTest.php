<?php
declare(strict_types=1);

namespace Tests\View;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class RedesignChecklistTest extends TestCase
{
    private function view(): View
    {
        return new View(\dirname(__DIR__, 2) . '/templates');
    }

    public function test_generated_assets_exist_for_redesign(): void
    {
        $root = \dirname(__DIR__, 2);
        foreach ([
            '/public/assets/generated/crush-mascot.png',
            '/public/assets/generated/invite-envelope.png',
            '/public/assets/generated/sent-heart.png',
            '/public/assets/generated/vibe-stickers.png',
        ] as $asset) {
            $this->assertFileExists($root . $asset, $asset);
        }
    }

    public function test_landing_uses_generated_art_without_duplicate_preview(): void
    {
        $html = $this->view()->render('landing/home', ['title' => 'Crush', 'csrf' => 'x']);
        $this->assertStringContainsString('/assets/generated/crush-mascot.png', $html);
        $this->assertStringContainsString('data-redesign-page="landing"', $html);
        $this->assertStringContainsString('class="card start-card"', $html);
        $this->assertStringContainsString('Ask your crush out', $html);
        $this->assertStringContainsString('Start my invite', $html);
        $this->assertStringNotContainsString('class="hero-points"', $html);
        $this->assertStringNotContainsString('ticket-steps', $html);
        $this->assertStringNotContainsString('Real life, but make it a date', $html);
        $this->assertStringContainsString('English', $html);
    }

    public function test_landing_language_switcher_does_not_overwrite_name_value(): void
    {
        $html = $this->view()->render('landing/home', ['title' => 'Crush', 'csrf' => 'x', 'lang' => 'en']);

        $this->assertStringContainsString('name="name" value="" placeholder="your name"', $html);
        $this->assertStringContainsString('type="email" name="email" value="" placeholder="your email"', $html);
        $this->assertStringNotContainsString('name="name" value="ไทย"', $html);
    }

    public function test_landing_preserves_submitted_name_after_validation_error(): void
    {
        $html = $this->view()->render('landing/home', [
            'title' => 'Crush',
            'csrf' => 'x',
            'lang' => 'en',
            'name' => 'Alex',
            'email' => 'bad',
            'error' => 'Nope',
        ]);

        $this->assertStringContainsString('name="name" value="Alex" placeholder="your name"', $html);
        $this->assertStringContainsString('name="email" value="bad" placeholder="your email"', $html);
    }

    public function test_login_uses_generated_art(): void
    {
        $html = $this->view()->render('auth/login', ['title' => 'Sign in', 'csrf' => 'x']);
        $this->assertStringContainsString('/assets/generated/crush-mascot.png', $html);
        $this->assertStringContainsString('data-redesign-page="login"', $html);
        $this->assertStringContainsString('autocomplete="username"', $html);
    }

    public function test_invite_builder_has_preview_progress_and_sticker_art(): void
    {
        $html = $this->view()->render('invite/new', ['title' => 'New invite', 'csrf' => 'x', 'meals' => []]);
        $this->assertStringContainsString('/assets/generated/invite-envelope.png', $html);
        $this->assertStringContainsString('/assets/generated/vibe-stickers.png', $html);
        $this->assertStringContainsString('data-redesign-page="invite-builder"', $html);
        $this->assertStringContainsString('class="quest-progress"', $html);
    }

    public function test_share_confirmation_dashboard_and_reveal_use_generated_art_hooks(): void
    {
        $share = $this->view()->render('invite/created', ['link' => 'https://x.test/i/abc', 'invite' => [], 'shareLinks' => []]);
        $this->assertStringContainsString('/assets/generated/invite-envelope.png', $share);
        $this->assertStringContainsString('data-redesign-page="share"', $share);

        $confirmed = $this->view()->render('respond/confirmed', ['theme' => 'bubblegum', 'when' => 'Tomorrow', 'reveal' => null]);
        $this->assertStringContainsString('/assets/generated/sent-heart.png', $confirmed);
        $this->assertStringContainsString('data-redesign-page="confirmation"', $confirmed);

        $dashboard = $this->view()->render('invite/dashboard', ['title' => 'Your invites', 'invites' => [], 'appUrl' => '']);
        $this->assertStringContainsString('/assets/generated/invite-envelope.png', $dashboard);
        $this->assertStringContainsString('data-redesign-page="dashboard"', $dashboard);

        $reveal = $this->view()->render('reveal/response', ['state' => 'waiting']);
        $this->assertStringContainsString('/assets/generated/invite-envelope.png', $reveal);
        $this->assertStringContainsString('data-redesign-page="reveal"', $reveal);
    }

    public function test_public_favicon_exists(): void
    {
        $this->assertFileExists(\dirname(__DIR__, 2) . '/public/favicon.ico');
    }
}

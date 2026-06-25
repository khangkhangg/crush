<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Profile\ProfileController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ProfileFormTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    private function controller(Csrf $csrf): ProfileController
    {
        return new ProfileController(new View(\dirname(__DIR__, 2) . '/templates'), $csrf, new UserRepo($this->pdo(), $this->clock));
    }

    public function test_form_has_no_pronouns_and_has_selected_indicator(): void
    {
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('u@x.test', 'U', 'magic')['id'];
        $body = $this->controller(new Csrf(new ArrayStore()))->edit($uid)->body();
        $this->assertStringNotContainsString('name="pronouns"', $body);
        $this->assertStringContainsString('av-pick', $body);
        $this->assertStringContainsString('input:checked', $body);     // live selected indicator
        $this->assertStringContainsString('name="return_to"', $body);
    }

    public function test_save_redirects_to_safe_return_to(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('a@x.test', 'A', 'magic')['id'];
        $res = $this->controller($csrf)->save($uid, ['avatar_key' => 'fox', 'bio' => 'hi', 'return_to' => '/invites/tok/response'], $csrf->token());
        $this->assertSame(302, $res->status());
        $this->assertSame('/invites/tok/response', $res->headers()['Location']);
    }

    public function test_save_rejects_unsafe_return_to(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $uid = (new UserRepo($this->pdo(), $this->clock))->create('b@x.test', 'B', 'magic')['id'];
        $res = $this->controller($csrf)->save($uid, ['avatar_key' => 'fox', 'bio' => 'hi', 'return_to' => 'https://evil.com'], $csrf->token());
        $this->assertSame('/', $res->headers()['Location']);          // external URL ignored
    }
}

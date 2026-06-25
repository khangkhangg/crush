<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Profile\Avatars;
use App\Profile\AvatarStore;
use App\Profile\ProfileController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class ProfileControllerTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    private function controller(Csrf $csrf): ProfileController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        return new ProfileController($view, $csrf, new UserRepo($this->pdo(), $this->clock), new AvatarStore(sys_get_temp_dir() . '/crush_av_test'));
    }

    private function user(): int
    {
        $c = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return (new UserRepo($this->pdo(), $c))->create('me@x.test', 'Me', 'magic')['id'];
    }

    public function test_edit_redirects_when_logged_out(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->edit(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_edit_renders_form_with_csrf_and_avatars(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->edit($this->user());
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($csrf->token(), $res->body());
        $this->assertStringContainsString('name="avatar_key"', $res->body());
    }

    public function test_save_rejects_bad_csrf(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->save($this->user(), ['avatar_key' => Avatars::default()], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_save_persists_profile_and_redirects(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $id = $this->user();
        $res = $ctrl->save($id, [
            'avatar_key' => Avatars::keys()[1] ?? Avatars::default(),
            'bio' => 'hi', 'contact' => '@me',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $reloaded = (new UserRepo($this->pdo(), $this->clock))->findById($id);
        $this->assertTrue(UserRepo::isProfileComplete($reloaded));
    }

    public function test_save_coerces_invalid_avatar_to_default(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $id = $this->user();
        $ctrl->save($id, ['avatar_key' => 'bogus', 'bio' => ''], $csrf->token());
        $reloaded = (new UserRepo($this->pdo(), $this->clock))->findById($id);
        $this->assertSame(Avatars::default(), $reloaded['avatar_key']);
    }

    public function test_password_page_redirects_when_logged_out(): void
    {
        $res = $this->controller(new Csrf(new ArrayStore()))->editPassword(null);
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_save_password_updates_hash(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $ctrl = $this->controller($csrf);
        $id = $this->user();

        $res = $ctrl->savePassword($id, [
            'password' => 'newpass1',
            'password_confirm' => 'newpass1',
        ], $csrf->token());

        $this->assertSame(302, $res->status());
        $this->assertSame('/profile', $res->headers()['Location']);
        $reloaded = (new UserRepo($this->pdo(), $this->clock))->findById($id);
        $this->assertTrue(password_verify('newpass1', (string) $reloaded['password_hash']));
    }

    public function test_save_password_rejects_mismatch(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $res = $this->controller($csrf)->savePassword($this->user(), [
            'password' => 'newpass1',
            'password_confirm' => 'different',
        ], $csrf->token());

        $this->assertSame(422, $res->status());
        $this->assertStringContainsString('Passwords do not match.', $res->body());
    }
}

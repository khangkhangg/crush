<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\GoogleAuth;
use App\Auth\GoogleController;
use App\Auth\OAuthProvider;
use App\Auth\OAuthUser;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeOAuthProvider;
use Tests\Support\FrozenClock;

final class GoogleControllerTest extends DatabaseTestCase
{
    private function make(ArrayStore $store, Session $session, bool $configured = true): GoogleController
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);
        $provider = new FakeOAuthProvider(new OAuthUser('g-1', 'g@x.test', 'Gee', null));
        $google = new GoogleAuth($provider, $users);
        return new GoogleController($google, $session, $store, $configured);
    }

    public function test_redirect_stores_state_and_points_to_provider(): void
    {
        $store = new ArrayStore();
        $res = $this->make($store, new Session(new ArrayStore()))->redirect();
        $this->assertSame(302, $res->status());
        $state = $store->get('oauth_state');
        $this->assertNotEmpty($state);
        $this->assertStringContainsString(rawurlencode((string) $state), $res->headers()['Location']);
    }

    public function test_callback_with_matching_state_logs_in(): void
    {
        $store = new ArrayStore();
        $session = new Session(new ArrayStore());
        $ctrl = $this->make($store, $session);
        $store->set('oauth_state', 'abc123');

        $res = $ctrl->callback('code-1', 'abc123');
        $this->assertSame(302, $res->status());
        $this->assertSame('/', $res->headers()['Location']);
        $this->assertTrue($session->check());
    }

    public function test_callback_with_bad_state_is_rejected(): void
    {
        $store = new ArrayStore();
        $session = new Session(new ArrayStore());
        $ctrl = $this->make($store, $session);
        $store->set('oauth_state', 'expected');

        $res = $ctrl->callback('code-1', 'attacker');
        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/login', $res->headers()['Location']);
        $this->assertFalse($session->check());
    }

    public function test_not_configured_redirects_to_login(): void
    {
        $res = $this->make(new ArrayStore(), new Session(new ArrayStore()), false)->redirect();
        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/login', $res->headers()['Location']);
    }

    public function test_callback_exception_redirects_to_login_error_and_session_not_logged_in(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $users = new UserRepo($this->pdo(), $clock);

        $throwingProvider = new class implements OAuthProvider {
            public function authUrl(string $state): string
            {
                return 'https://accounts.google.test/auth?state=' . urlencode($state);
            }

            public function fetchUser(string $code): OAuthUser
            {
                throw new \RuntimeException('OAuth upstream failure');
            }
        };

        $google = new GoogleAuth($throwingProvider, $users);
        $store = new ArrayStore();
        $session = new Session(new ArrayStore());
        $ctrl = new GoogleController($google, $session, $store, true);
        $store->set('oauth_state', 'valid-state');

        $res = $ctrl->callback('any-code', 'valid-state');
        $this->assertSame(302, $res->status());
        $this->assertSame('/login?e=oauth', $res->headers()['Location']);
        $this->assertFalse($session->check());
    }
}

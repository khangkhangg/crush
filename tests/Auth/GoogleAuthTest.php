<?php
declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\GoogleAuth;
use App\Auth\OAuthUser;
use App\Auth\UserRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeOAuthProvider;
use Tests\Support\FrozenClock;

final class GoogleAuthTest extends DatabaseTestCase
{
    private function repo(): UserRepo
    {
        return new UserRepo($this->pdo(), new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function test_new_google_user_is_created(): void
    {
        $provider = new FakeOAuthProvider(new OAuthUser('g-1', 'new@x.test', 'New', 'http://img/n.png'));
        $auth = new GoogleAuth($provider, $this->repo());

        $user = $auth->handleCallback('code-123');
        $this->assertSame('new@x.test', $user['email']);
        $this->assertSame('g-1', $user['google_id']);
        $this->assertSame('google', $user['auth_provider']);
    }

    public function test_existing_email_gets_google_linked(): void
    {
        $repo = $this->repo();
        $existing = $repo->create('dupe@x.test', 'Dee', 'magic');

        $provider = new FakeOAuthProvider(new OAuthUser('g-2', 'dupe@x.test', 'Dee', 'http://img/d.png'));
        $auth = new GoogleAuth($provider, $repo);

        $user = $auth->handleCallback('code-xyz');
        $this->assertSame($existing['id'], $user['id']);
        $this->assertSame('g-2', $user['google_id']);
    }

    public function test_returning_google_user_is_found(): void
    {
        $repo = $this->repo();
        $repo->create('ret@x.test', 'Ren', 'google', 'g-3', null);

        $provider = new FakeOAuthProvider(new OAuthUser('g-3', 'ret@x.test', 'Ren', null));
        $auth = new GoogleAuth($provider, $repo);

        $user = $auth->handleCallback('code-aaa');
        $this->assertSame('g-3', $user['google_id']);
    }

    public function test_auth_url_passes_state_through(): void
    {
        $provider = new FakeOAuthProvider(new OAuthUser('g-1', 'a@x.test', 'A', null));
        $auth = new GoogleAuth($provider, $this->repo());
        $url = $auth->authUrl('state-token');
        $this->assertStringContainsString('state-token', $url);
        $this->assertSame('state-token', $provider->lastState);
    }
}

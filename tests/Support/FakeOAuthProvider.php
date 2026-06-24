<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Auth\OAuthProvider;
use App\Auth\OAuthUser;

final class FakeOAuthProvider implements OAuthProvider
{
    public ?string $lastState = null;

    public function __construct(private OAuthUser $user) {}

    public function authUrl(string $state): string
    {
        $this->lastState = $state;
        return 'https://accounts.google.test/o/oauth2/auth?state=' . urlencode($state);
    }

    public function fetchUser(string $code): OAuthUser
    {
        return $this->user;
    }
}

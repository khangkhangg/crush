<?php
declare(strict_types=1);

namespace App\Auth;

interface OAuthProvider
{
    public function authUrl(string $state): string;
    public function fetchUser(string $code): OAuthUser;
}

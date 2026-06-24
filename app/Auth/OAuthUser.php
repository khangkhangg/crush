<?php
declare(strict_types=1);

namespace App\Auth;

final class OAuthUser
{
    public function __construct(
        public string $googleId,
        public string $email,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}
}

<?php
declare(strict_types=1);

namespace App\Auth;

final class GoogleAuth
{
    public function __construct(private OAuthProvider $provider, private UserRepo $users) {}

    public function authUrl(string $state): string
    {
        return $this->provider->authUrl($state);
    }

    public function handleCallback(string $code): array
    {
        $profile = $this->provider->fetchUser($code);

        $existing = $this->users->findByGoogleId($profile->googleId);
        if ($existing !== null) {
            return $existing;
        }

        $byEmail = $this->users->findByEmail($profile->email);
        if ($byEmail !== null) {
            $this->users->linkGoogle($byEmail['id'], $profile->googleId, $profile->avatarUrl);
            return $this->users->findById($byEmail['id']);
        }

        return $this->users->create(
            $profile->email,
            $profile->name,
            'google',
            $profile->googleId,
            $profile->avatarUrl,
        );
    }
}

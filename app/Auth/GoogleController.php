<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Response;
use App\Core\Store;

final class GoogleController
{
    private const STATE_KEY = 'oauth_state';

    public function __construct(
        private GoogleAuth $google,
        private Session $session,
        private Store $store,
        private bool $configured,
    ) {}

    public function redirect(): Response
    {
        if (!$this->configured) {
            return (new Response('', 302))->withHeader('Location', '/login?e=google');
        }
        $state = bin2hex(random_bytes(16));
        $this->store->set(self::STATE_KEY, $state);
        return (new Response('', 302))->withHeader('Location', $this->google->authUrl($state));
    }

    public function callback(?string $code, ?string $state): Response
    {
        $expected = $this->store->get(self::STATE_KEY);
        $this->store->set(self::STATE_KEY, null);

        if (!is_string($code) || !is_string($state) || !is_string($expected) || !hash_equals($expected, $state)) {
            return (new Response('', 302))->withHeader('Location', '/login?e=oauth');
        }

        try {
            $user = $this->google->handleCallback($code);
            $this->session->login((int) $user['id']);
        } catch (\Throwable) {
            return (new Response('', 302))->withHeader('Location', '/login?e=oauth');
        }
        return (new Response('', 302))->withHeader('Location', '/');
    }
}

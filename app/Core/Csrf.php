<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private Store $store) {}

    public function token(): string
    {
        $token = $this->store->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->store->set(self::KEY, $token);
        }
        return $token;
    }

    public function validate(?string $candidate): bool
    {
        $token = $this->store->get(self::KEY);
        if (!is_string($token) || !is_string($candidate)) {
            return false;
        }
        return hash_equals($token, $candidate);
    }
}

<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\RegeneratesId;
use App\Core\Store;

final class Session
{
    private const KEY = 'uid';

    public function __construct(private Store $store) {}

    public function login(int $userId): void
    {
        if ($this->store instanceof RegeneratesId) {
            $this->store->regenerateId();
        }
        $this->store->set(self::KEY, $userId);
    }

    public function userId(): ?int
    {
        $v = $this->store->get(self::KEY);
        return is_int($v) ? $v : null;
    }

    public function check(): bool
    {
        return $this->userId() !== null;
    }

    public function logout(): void
    {
        $this->store->set(self::KEY, null);
    }
}

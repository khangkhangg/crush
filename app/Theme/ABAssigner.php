<?php
declare(strict_types=1);

namespace App\Theme;

use App\Invite\InviteRepo;

final class ABAssigner
{
    /** @var \Closure(int):int */
    private \Closure $randInt;

    public function __construct(
        private ThemeRepo $themes,
        private InviteRepo $invites,
        ?\Closure $randInt = null,
    ) {
        $this->randInt = $randInt ?? static fn(int $max): int => random_int(0, $max);
    }

    public function assignTo(array $invite): string
    {
        $current = $invite['theme_key'] ?? null;
        if (is_string($current) && $current !== '' && $this->themes->exists($current)) {
            return $current;
        }

        $active = $this->themes->listActive();
        if ($active === []) {
            throw new \RuntimeException('No active themes to assign.');
        }

        $total = array_sum(array_map(static fn(array $t): int => max(1, (int) $t['weight']), $active));
        $r = ($this->randInt)($total - 1);

        $cursor = 0;
        $chosen = $active[array_key_last($active)]['key'];
        foreach ($active as $theme) {
            $cursor += max(1, (int) $theme['weight']);
            if ($r < $cursor) {
                $chosen = $theme['key'];
                break;
            }
        }

        $this->invites->setTheme((int) $invite['id'], $chosen);
        return $chosen;
    }
}

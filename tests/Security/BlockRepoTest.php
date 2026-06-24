<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\BlockRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class BlockRepoTest extends DatabaseTestCase
{
    public function test_block_is_idempotent_and_queryable(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repo = new BlockRepo($this->pdo(), $clock);

        $this->assertFalse($repo->isBlocked(1, 'c@x.test'));
        $repo->block(1, 'c@x.test', 'reported');
        $repo->block(1, 'c@x.test', 'reported'); // idempotent, no error
        $this->assertTrue($repo->isBlocked(1, 'c@x.test'));
        $this->assertFalse($repo->isBlocked(2, 'c@x.test'));
        $this->assertCount(1, $repo->recent());
    }
}

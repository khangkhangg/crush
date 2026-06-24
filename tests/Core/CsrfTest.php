<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\ArrayStore;
use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function test_token_is_stable_within_session(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $t1 = $csrf->token();
        $t2 = $csrf->token();
        $this->assertSame($t1, $t2);
        $this->assertGreaterThanOrEqual(32, strlen($t1));
    }

    public function test_validate_accepts_correct_and_rejects_wrong(): void
    {
        $csrf = new Csrf(new ArrayStore());
        $token = $csrf->token();
        $this->assertTrue($csrf->validate($token));
        $this->assertFalse($csrf->validate('wrong'));
        $this->assertFalse($csrf->validate(null));
    }
}

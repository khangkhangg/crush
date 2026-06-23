<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_get_returns_value_or_default(): void
    {
        $c = new Config(['app_env' => 'testing']);
        $this->assertSame('testing', $c->get('app_env'));
        $this->assertSame('fallback', $c->get('missing', 'fallback'));
        $this->assertNull($c->get('missing'));
    }

    public function test_from_env_maps_known_keys(): void
    {
        $c = Config::fromEnv(['APP_ENV' => 'prod', 'APP_URL' => 'https://x.test']);
        $this->assertSame('prod', $c->get('app_env'));
        $this->assertSame('https://x.test', $c->get('app_url'));
    }
}

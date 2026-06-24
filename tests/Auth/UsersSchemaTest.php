<?php
declare(strict_types=1);

namespace Tests\Auth;

use Tests\Support\DatabaseTestCase;

final class UsersSchemaTest extends DatabaseTestCase
{
    public function test_users_and_magic_tokens_exist_with_key_columns(): void
    {
        $cols = fn(string $t) => array_column(
            $this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(),
            'Field'
        );

        $users = $cols('users');
        foreach (['id', 'email', 'name', 'auth_provider', 'google_id', 'avatar_url', 'is_admin', 'created_at'] as $c) {
            $this->assertContains($c, $users, "users.$c missing");
        }

        $tokens = $cols('magic_tokens');
        foreach (['id', 'user_id', 'token_hash', 'expires_at', 'used_at'] as $c) {
            $this->assertContains($c, $tokens, "magic_tokens.$c missing");
        }
    }
}

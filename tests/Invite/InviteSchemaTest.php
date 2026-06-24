<?php
declare(strict_types=1);

namespace Tests\Invite;

use Tests\Support\DatabaseTestCase;

final class InviteSchemaTest extends DatabaseTestCase
{
    public function test_tables_and_key_columns_exist(): void
    {
        $cols = fn(string $t) => array_column(
            $this->pdo()->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(),
            'Field'
        );

        foreach (['id', 'public_token', 'sender_id', 'crush_email', 'crush_name',
                  'is_anonymous', 'reveal_on_response', 'date_mode', 'status',
                  'theme_key', 'message', 'created_at', 'expires_at'] as $c) {
            $this->assertContains($c, $cols('invites'), "invites.$c");
        }
        foreach (['id', 'invite_id', 'start_at', 'end_at'] as $c) {
            $this->assertContains($c, $cols('invite_date_options'), "invite_date_options.$c");
        }
        foreach (['id', 'invite_id', 'chosen_start', 'chosen_end', 'meal_choice',
                  'meal_wish', 'crush_contact', 'pickup_raw', 'pickup_name',
                  'pickup_address', 'pickup_clean_url', 'created_at'] as $c) {
            $this->assertContains($c, $cols('responses'), "responses.$c");
        }
    }
}

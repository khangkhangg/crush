<?php
declare(strict_types=1);
namespace Tests\I18n;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;
final class SenderFlowSeedTest extends DatabaseTestCase
{
    public function test_core_sender_strings_translated_in_all_langs(): void
    {
        $keys = ['Send a crush invite', 'Create my invite', 'Your invites', 'Your invite is ready', 'Your answer is on its way', 'Dinner'];
        foreach (['vi','es','zh','hi','pt','fr','ko','ja','th'] as $lang) {
            $tr = new Translator($this->pdo(), $lang);
            foreach ($keys as $k) {
                $val = $tr->t($k);
                $this->assertNotSame($k, $val, "$lang missing: $k");
                $this->assertNotSame('', trim($val));
            }
        }
    }
}

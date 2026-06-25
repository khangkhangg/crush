<?php
declare(strict_types=1);
namespace Tests\I18n;
use App\I18n\Translator;
use Tests\Support\DatabaseTestCase;
final class ResponderFlowSeedTest extends DatabaseTestCase
{
    public function test_core_responder_strings_translated_in_all_langs(): void
    {
        $keys = ['What are you craving?', 'Send my answer', 'Make it yours', 'Save my profile', 'Sign in', 'They picked'];
        foreach (['vi','es','zh','hi','pt','fr','ko','ja','th'] as $lang) {
            $tr = new Translator($this->pdo(), $lang);
            foreach ($keys as $k) {
                $this->assertNotSame($k, $tr->t($k), "$lang missing: $k");
                $this->assertNotSame('', trim($tr->t($k)));
            }
        }
    }
}

<?php
declare(strict_types=1);

namespace Tests\Ics;

use App\Ics\IcsBuilder;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrozenClock;

final class IcsBuilderTest extends TestCase
{
    private function builder(): IcsBuilder
    {
        return new IcsBuilder(new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function test_builds_valid_vevent(): void
    {
        $ics = $this->builder()->build([
            'uid'         => 'abc-123',
            'summary'     => 'Date with Sue',
            'start'       => '2026-02-10 19:00:00',
            'end'         => '2026-02-10 21:00:00',
            'location'    => 'Tartine Bakery, 1 Main St',
            'description' => 'Dinner; sushi',
        ]);

        $this->assertStringContainsString("BEGIN:VCALENDAR", $ics);
        $this->assertStringContainsString("BEGIN:VEVENT", $ics);
        $this->assertStringContainsString("UID:abc-123", $ics);
        $this->assertStringContainsString("SUMMARY:Date with Sue", $ics);
        $this->assertStringContainsString("DTSTART:20260210T190000Z", $ics);
        $this->assertStringContainsString("DTEND:20260210T210000Z", $ics);
        $this->assertStringContainsString("DTSTAMP:20260101T000000Z", $ics);
        // ICS escaping: comma and semicolon are backslash-escaped.
        $this->assertStringContainsString("LOCATION:Tartine Bakery\\, 1 Main St", $ics);
        $this->assertStringContainsString("DESCRIPTION:Dinner\\; sushi", $ics);
        $this->assertStringContainsString("BEGIN:VALARM", $ics);
        $this->assertStringContainsString("TRIGGER:-PT1H", $ics);
        $this->assertStringContainsString("\r\n", $ics);
    }

    public function test_optional_fields_omitted_when_null(): void
    {
        $ics = $this->builder()->build([
            'uid' => 'x', 'summary' => 'S',
            'start' => '2026-02-10 19:00:00', 'end' => '2026-02-10 20:00:00',
            'location' => null, 'description' => null,
        ]);
        $this->assertStringNotContainsString("LOCATION:", $ics);
        $this->assertStringNotContainsString("DESCRIPTION:", $ics);
    }
}

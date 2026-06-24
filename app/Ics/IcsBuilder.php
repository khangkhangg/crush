<?php
declare(strict_types=1);

namespace App\Ics;

use App\Core\Clock;

final class IcsBuilder
{
    public function __construct(private Clock $clock) {}

    public function build(array $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Crush//Date Invite//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $this->text((string) $event['uid']),
            'DTSTAMP:' . $this->stamp($this->clock->now()->format('Y-m-d H:i:s')),
            'DTSTART:' . $this->stamp((string) $event['start']),
            'DTEND:' . $this->stamp((string) $event['end']),
            'SUMMARY:' . $this->text((string) $event['summary']),
        ];

        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . $this->text((string) $event['location']);
        }
        if (!empty($event['description'])) {
            $lines[] = 'DESCRIPTION:' . $this->text((string) $event['description']);
        }

        $lines = array_merge($lines, [
            'BEGIN:VALARM',
            'TRIGGER:-PT1H',
            'ACTION:DISPLAY',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function stamp(string $ymdhis): string
    {
        return (new \DateTimeImmutable($ymdhis, new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
    }

    private function text(string $v): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $v
        );
    }
}

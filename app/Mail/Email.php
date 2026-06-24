<?php
declare(strict_types=1);

namespace App\Mail;

final class Email
{
    /** @param array<int,array{filename:string,mime:string,content:string}> $attachments */
    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public array $attachments = [],
    ) {}
}

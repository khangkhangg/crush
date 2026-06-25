<?php
declare(strict_types=1);

namespace App\Mail;

interface Mailer
{
    /** @throws \RuntimeException on send failure. */
    public function send(Email $email): void;

    /** @throws \RuntimeException when credentials are invalid/unreachable. */
    public function verify(): void;
}

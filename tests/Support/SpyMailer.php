<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Mail\Email;
use App\Mail\Mailer;

final class SpyMailer implements Mailer
{
    /** @var Email[] */
    public array $sent = [];

    public function send(Email $email): void
    {
        $this->sent[] = $email;
    }
}

<?php
declare(strict_types=1);

namespace App\Mail;

final class FailoverMailer implements Mailer
{
    public function __construct(
        private Mailer $primary,
        private ?Mailer $backup,
        private ?\Closure $onFailure = null,
    ) {}

    public function send(Email $email): void
    {
        try {
            $this->primary->send($email);
            return;
        } catch (\Throwable $primaryError) {
            if ($this->backup !== null) {
                try {
                    $this->backup->send($email);
                    return;
                } catch (\Throwable $backupError) {
                    $this->fail($email, $backupError);
                }
            }
            $this->fail($email, $primaryError);
        }
    }

    public function verify(): void
    {
        $this->primary->verify();
    }

    private function fail(Email $email, \Throwable $error): void
    {
        if ($this->onFailure !== null) {
            ($this->onFailure)($error, $email);
        }
        throw new \RuntimeException('All mail providers failed: ' . $error->getMessage(), 0, $error);
    }
}

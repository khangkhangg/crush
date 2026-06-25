<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Mail\FailoverMailer;
use App\Mail\MailerFactory;
use App\Mail\MailjetMailer;
use App\Mail\PhpMailMailer;
use App\Mail\ResendMailer;
use App\Mail\SmtpMailer;
use App\Settings\SettingsRepo;
use Tests\Support\DatabaseTestCase;

final class MailerFactoryTest extends DatabaseTestCase
{
    private function settings(): SettingsRepo
    {
        return new SettingsRepo($this->pdo());
    }

    public function test_defaults_to_php_mailer(): void
    {
        $this->assertInstanceOf(PhpMailMailer::class, MailerFactory::make($this->settings()));
    }

    public function test_selects_resend(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'resend');
        $s->set('resend_api_key', 're_123');
        $s->set('from_email', 'love@crush.app');
        $this->assertInstanceOf(ResendMailer::class, MailerFactory::make($s));
    }

    public function test_selects_smtp(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'smtp');
        $s->set('smtp_host', 'smtp.test');
        $this->assertInstanceOf(SmtpMailer::class, MailerFactory::make($s));
    }

    public function test_selects_mailjet(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'mailjet');
        $s->set('mailjet_api_key', 'key123');
        $s->set('mailjet_secret_key', 'secret123');
        $this->assertInstanceOf(MailjetMailer::class, MailerFactory::make($s));
    }

    public function test_wraps_in_failover_when_backup_set(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'resend');
        $s->set('resend_api_key', 're_123');
        $s->set('mail_backup', 'mailjet');
        $s->set('mailjet_api_key', 'key123');
        $s->set('mailjet_secret_key', 'secret123');
        $this->assertInstanceOf(FailoverMailer::class, MailerFactory::make($s));
    }

    public function test_no_failover_when_backup_is_none(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'resend');
        $s->set('resend_api_key', 're_123');
        $s->set('mail_backup', 'none');
        $this->assertInstanceOf(ResendMailer::class, MailerFactory::make($s));
    }

    public function test_no_failover_when_backup_equals_primary(): void
    {
        $s = $this->settings();
        $s->set('mail_driver', 'resend');
        $s->set('resend_api_key', 're_123');
        $s->set('mail_backup', 'resend');
        $this->assertInstanceOf(ResendMailer::class, MailerFactory::make($s));
    }
}

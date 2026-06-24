<?php
declare(strict_types=1);

namespace App\Mail;

use App\Settings\SettingsRepo;

final class MailerFactory
{
    public static function make(SettingsRepo $settings): Mailer
    {
        $fromEmail = $settings->get('from_email', 'noreply@localhost');
        $fromName  = $settings->get('from_name', 'Crush');

        return match ($settings->get('mail_driver', 'php')) {
            'resend' => new ResendMailer(
                (string) $settings->get('resend_api_key', ''),
                (string) $fromEmail,
                (string) $fromName,
            ),
            'smtp' => new SmtpMailer(
                (string) $settings->get('smtp_host', ''),
                (int) ($settings->get('smtp_port', '587')),
                (string) $settings->get('smtp_user', ''),
                (string) $settings->get('smtp_pass', ''),
                (string) $fromEmail,
                (string) $fromName,
                (string) $settings->get('smtp_encryption', 'tls'),
            ),
            default => new PhpMailMailer((string) $fromEmail, (string) $fromName),
        };
    }
}

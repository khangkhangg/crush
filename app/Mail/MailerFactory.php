<?php
declare(strict_types=1);

namespace App\Mail;

use App\Settings\SettingsRepo;

final class MailerFactory
{
    public static function make(SettingsRepo $settings): Mailer
    {
        $fromEmail = (string) $settings->get('from_email', 'noreply@localhost');
        $fromName  = (string) $settings->get('from_name', 'Crush');
        $primaryDriver = (string) $settings->get('mail_driver', 'php');
        $primary = self::build($primaryDriver, $settings, $fromEmail, $fromName);

        $backupDriver = (string) $settings->get('mail_backup', 'none');
        if ($backupDriver !== '' && $backupDriver !== 'none' && $backupDriver !== $primaryDriver) {
            $backup = self::build($backupDriver, $settings, $fromEmail, $fromName);
            return new FailoverMailer($primary, $backup);
        }
        return $primary;
    }

    public static function build(string $driver, SettingsRepo $settings, string $fromEmail, string $fromName): Mailer
    {
        return match ($driver) {
            'resend' => new ResendMailer(
                (string) $settings->get('resend_api_key', ''), $fromEmail, $fromName,
            ),
            'mailjet' => new MailjetMailer(
                (string) $settings->get('mailjet_api_key', ''),
                (string) $settings->get('mailjet_secret_key', ''),
                $fromEmail, $fromName,
            ),
            'smtp' => new SmtpMailer(
                (string) $settings->get('smtp_host', ''),
                (int) ($settings->get('smtp_port', '587')),
                (string) $settings->get('smtp_user', ''),
                (string) $settings->get('smtp_pass', ''),
                $fromEmail, $fromName,
                (string) $settings->get('smtp_encryption', 'tls'),
            ),
            default => new PhpMailMailer($fromEmail, $fromName),
        };
    }
}

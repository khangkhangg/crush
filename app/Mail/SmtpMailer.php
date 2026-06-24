<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer implements Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $fromEmail,
        private string $fromName,
        private string $encryption = 'tls',
    ) {}

    public function send(Email $email): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            if ($this->username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }
            if ($this->encryption !== '') {
                $mail->SMTPSecure = $this->encryption;
            }
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($email->to);
            $mail->isHTML(true);
            $mail->Subject = $email->subject;
            $mail->Body    = $email->html;
            foreach ($email->attachments as $att) {
                $mail->addStringAttachment($att['content'], $att['filename'], PHPMailer::ENCODING_BASE64, $att['mime']);
            }
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new \RuntimeException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }
}

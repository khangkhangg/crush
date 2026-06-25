<?php
declare(strict_types=1);

namespace App\Mail;

final class PhpMailMailer implements Mailer
{
    public function __construct(private string $fromEmail, private string $fromName) {}

    public function verify(): void {}

    public function send(Email $email): void
    {
        $boundary = 'crush_' . bin2hex(random_bytes(8));
        $from = sprintf('%s <%s>', $this->fromName, $this->fromEmail);
        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        $body .= $email->html . "\r\n";
        foreach ($email->attachments as $att) {
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: ' . $att['mime'] . "; name=\"{$att['filename']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $att['filename'] . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($att['content'])) . "\r\n";
        }
        $body .= "--{$boundary}--";

        if (!mail($email->to, $email->subject, $body, implode("\r\n", $headers))) {
            throw new \RuntimeException('mail() failed for ' . $email->to);
        }
    }
}

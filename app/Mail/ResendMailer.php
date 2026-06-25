<?php
declare(strict_types=1);

namespace App\Mail;

final class ResendMailer implements Mailer
{
    public function __construct(
        private string $apiKey,
        private string $fromEmail,
        private string $fromName,
    ) {}

    public function verify(): void
    {
        $ch = curl_init('https://api.resend.com/domains');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($res === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException('Resend verify failed (' . $status . '): ' . $err . ' ' . (string) $res);
        }
    }

    public function send(Email $email): void
    {
        $payload = [
            'from'    => sprintf('%s <%s>', $this->fromName, $this->fromEmail),
            'to'      => [$email->to],
            'subject' => $email->subject,
            'html'    => $email->html,
        ];
        foreach ($email->attachments as $att) {
            $payload['attachments'][] = [
                'filename' => $att['filename'],
                'content'  => base64_encode($att['content']),
            ];
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($res === false || $status >= 300) {
            throw new \RuntimeException('Resend failed (' . $status . '): ' . $err . ' ' . (string) $res);
        }
    }
}

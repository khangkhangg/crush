<?php
declare(strict_types=1);

namespace App\Mail;

final class MailjetMailer implements Mailer
{
    public function __construct(
        private string $apiKey,
        private string $secretKey,
        private string $fromEmail,
        private string $fromName,
    ) {}

    public function send(Email $email): void
    {
        $message = [
            'From'     => ['Email' => $this->fromEmail, 'Name' => $this->fromName],
            'To'       => [['Email' => $email->to]],
            'Subject'  => $email->subject,
            'HTMLPart' => $email->html,
        ];
        foreach ($email->attachments as $att) {
            $message['Attachments'][] = [
                'ContentType'   => $att['mime'] ?? 'application/octet-stream',
                'Filename'      => $att['filename'],
                'Base64Content' => base64_encode($att['content']),
            ];
        }
        $this->request('https://api.mailjet.com/v3.1/send', 'POST', json_encode(['Messages' => [$message]]));
    }

    public function verify(): void
    {
        if ($this->apiKey === '' || $this->secretKey === '') {
            throw new \RuntimeException('Mailjet API key/secret is not set.');
        }
        $this->request('https://api.mailjet.com/v3/REST/sender?Limit=1', 'GET', null);
    }

    private function request(string $url, string $method, ?string $body): void
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->apiKey . ':' . $this->secretKey,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = (string) $body;
        }
        curl_setopt_array($ch, $opts);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($res === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException('Mailjet failed (' . $status . '): ' . $err . ' ' . (string) $res);
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Mail;

use App\Core\View;
use App\Ics\IcsBuilder;

final class Postman
{
    public function __construct(
        private Mailer $mailer,
        private IcsBuilder $ics,
        private View $view,
        private string $appUrl,
    ) {}

    public function sendInvite(array $invite): bool
    {
        $senderLabel = (int) ($invite['is_anonymous'] ?? 0) === 1 ? 'a secret admirer' : 'someone';
        $html = $this->view->render('email/invite', [
            'senderLabel' => $senderLabel,
            'message'     => $invite['message'] ?? null,
            'link'        => rtrim($this->appUrl, '/') . '/i/' . $invite['public_token'],
            'theme'       => $invite['theme_key'] ?? 'bubblegum',
            'unsubscribe' => rtrim($this->appUrl, '/') . '/unsubscribe/' . $invite['public_token'],
        ]);
        return $this->dispatch(new Email(
            (string) $invite['crush_email'],
            'You have a crush invite',
            $html
        ));
    }

    public function sendResult(array $invite, array $response, array $sender): bool
    {
        $crushName = $invite['crush_name'] ?: 'your crush';
        $descParts = array_filter([
            $response['meal_choice'] ?? null,
            !empty($response['meal_wish']) ? 'wish: ' . $response['meal_wish'] : null,
            !empty($response['crush_contact']) ? 'contact: ' . $response['crush_contact'] : null,
        ]);
        $location = trim(implode(', ', array_filter([
            $response['pickup_name'] ?? null,
            $response['pickup_address'] ?? null,
        ])));

        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crushName,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $location !== '' ? $location : null,
            'description' => $descParts !== [] ? implode('; ', $descParts) : null,
        ]);

        $html = $this->view->render('email/result', [
            'crushName' => $crushName,
            'response'  => $response,
            'mapHref'   => self::safeHref($response['pickup_clean_url'] ?? null),
            'location'  => $location,
        ]);

        return $this->dispatch(new Email(
            (string) $sender['email'],
            $crushName . ' answered your invite',
            $html,
            [['filename' => 'Date.ics', 'mime' => 'text/calendar', 'content' => $ics]]
        ));
    }

    public static function safeHref(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return ($scheme === 'http' || $scheme === 'https') ? $url : null;
    }

    private function dispatch(Email $email): bool
    {
        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            error_log('Crush mail failed: ' . $e->getMessage());
            return false;
        }
    }
}

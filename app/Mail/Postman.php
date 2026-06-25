<?php
declare(strict_types=1);

namespace App\Mail;

use App\Ics\IcsBuilder;

final class Postman
{
    public function __construct(
        private Mailer $mailer,
        private IcsBuilder $ics,
        private EmailTemplateRepo $templates,
        private string $appUrl,
    ) {}

    public function sendWelcome(string $email, ?string $name, string $loginLink, string $lang = 'en'): bool
    {
        return $this->dispatchTemplate($email, 'welcome', $lang, [
            'name' => $name ?? '',
            'link' => $loginLink,
        ]);
    }

    public function sendMagic(string $email, string $loginLink, string $lang = 'en'): bool
    {
        return $this->dispatchTemplate($email, 'magic', $lang, ['link' => $loginLink]);
    }

    public function sendInvite(array $invite): bool
    {
        $base = rtrim($this->appUrl, '/');
        return $this->dispatchTemplate((string) $invite['crush_email'], 'invite', (string) ($invite['lang'] ?? 'en'), [
            'senderLabel' => (int) ($invite['is_anonymous'] ?? 0) === 1 ? 'a secret admirer' : 'someone',
            'message'     => (string) ($invite['message'] ?? ''),
            'link'        => $base . '/i/' . $invite['public_token'],
            'unsubscribe' => $base . '/unsubscribe/' . $invite['public_token'],
        ]);
    }

    public function sendResult(array $invite, array $response, array $sender): bool
    {
        $crush = $invite['crush_name'] ?: 'your crush';
        $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
        $descParts = array_filter([
            $response['meal_choice'] ?? null,
            !empty($response['meal_wish']) ? 'wish: ' . $response['meal_wish'] : null,
            !empty($response['crush_contact']) ? 'contact: ' . $response['crush_contact'] : null,
        ]);

        $ics = $this->ics->build([
            'uid'         => $invite['public_token'] . '@crush',
            'summary'     => 'Date with ' . $crush,
            'start'       => (string) $response['chosen_start'],
            'end'         => (string) $response['chosen_end'],
            'location'    => $place !== '' ? $place : null,
            'description' => $descParts !== [] ? implode('; ', $descParts) : null,
        ]);

        $rendered = $this->templates->render('result', (string) ($sender['lang'] ?? 'en'), [
            'crushName' => $crush,
            'when'      => (string) ($response['chosen_start'] ?? ''),
            'meal'      => (string) ($response['meal_choice'] ?? ''),
            'place'     => $place,
            'mapHref'   => self::safeHref($response['pickup_clean_url'] ?? null) ?? '',
        ]);

        return $this->dispatch(new Email(
            (string) $sender['email'],
            $rendered['subject'],
            $rendered['html'],
            [['filename' => 'Date.ics', 'mime' => 'text/calendar', 'content' => $ics]]
        ));
    }

    /** Confirmation email to the crush after they answer (only if we have their email). */
    public function sendConfirm(array $invite, array $response): bool
    {
        $crushEmail = trim((string) ($invite['crush_email'] ?? ''));
        if ($crushEmail === '') {
            return false;
        }
        $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
        return $this->dispatchTemplate($crushEmail, 'confirm', (string) ($invite['lang'] ?? 'en'), [
            'when'  => (string) ($response['chosen_start'] ?? ''),
            'meal'  => (string) ($response['meal_choice'] ?? ''),
            'place' => $place,
        ]);
    }

    public static function safeHref(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return ($scheme === 'http' || $scheme === 'https') ? $url : null;
    }

    private function dispatchTemplate(string $to, string $key, string $lang, array $vars): bool
    {
        try {
            $rendered = $this->templates->render($key, $lang, $vars);
        } catch (\Throwable $e) {
            error_log('Crush template render failed: ' . $e->getMessage());
            return false;
        }
        return $this->dispatch(new Email($to, $rendered['subject'], $rendered['html']));
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

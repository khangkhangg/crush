<?php
declare(strict_types=1);

namespace App\Auth;

final class GoogleProvider implements OAuthProvider
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
    ) {}

    public function authUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function fetchUser(string $code): OAuthUser
    {
        $token = $this->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);
        if (!isset($token['access_token'])) {
            throw new \RuntimeException('Google token exchange failed.');
        }
        $profile = $this->get('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
        if (!isset($profile['sub'], $profile['email'])) {
            throw new \RuntimeException('Google userinfo missing required fields.');
        }
        return new OAuthUser(
            (string) $profile['sub'],
            (string) $profile['email'],
            isset($profile['name']) ? (string) $profile['name'] : null,
            isset($profile['picture']) ? (string) $profile['picture'] : null,
        );
    }

    private function post(string $url, array $form): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_TIMEOUT        => 10,
        ]);
        return $this->exec($ch);
    }

    private function get(string $url, string $bearer): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $bearer],
            CURLOPT_TIMEOUT        => 10,
        ]);
        return $this->exec($ch);
    }

    private function exec(\CurlHandle $ch): array
    {
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Google request failed: ' . $err);
        }
        curl_close($ch);
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : [];
    }
}

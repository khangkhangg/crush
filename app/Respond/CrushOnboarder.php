<?php
declare(strict_types=1);

namespace App\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Mail\Postman;

final class CrushOnboarder
{
    public function __construct(
        private UserRepo $users,
        private MagicLink $magic,
        private Postman $postman,
        private string $appUrl,
    ) {}

    public function onboard(string $email, ?string $name, string $lang = 'en'): void
    {
        if ($this->users->findByEmail($email) !== null) {
            return; // existing account — never re-create or re-welcome
        }
        $user = $this->users->create($email, $name, 'magic');
        $this->users->setLang((int) $user['id'], $lang);
        $token = $this->magic->start($email);
        $link = rtrim($this->appUrl, '/') . '/auth/magic/' . $token;
        $this->postman->sendWelcome($email, $name, $link, $lang);
    }
}

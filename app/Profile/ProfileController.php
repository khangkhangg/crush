<?php
declare(strict_types=1);

namespace App\Profile;

use App\Auth\UserRepo;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;

final class ProfileController
{
    public function __construct(
        private View $view,
        private Csrf $csrf,
        private UserRepo $users,
    ) {}

    public function edit(?int $userId): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        $user = $this->users->findById($userId);
        return Response::html($this->view->render('profile/edit', [
            'title'   => 'Your profile',
            'csrf'    => $this->csrf->token(),
            'user'    => $user,
            'avatars' => Avatars::keys(),
        ]));
    }

    public function save(?int $userId, array $input, string $csrf): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        if (!$this->csrf->validate($csrf)) {
            $user = $this->users->findById($userId);
            return Response::html($this->view->render('profile/edit', [
                'title'   => 'Your profile',
                'csrf'    => $this->csrf->token(),
                'user'    => $user,
                'avatars' => Avatars::keys(),
                'error'   => 'Your session expired. Please try again.',
            ]), 400);
        }

        $avatar = (string) ($input['avatar_key'] ?? '');
        if (!Avatars::isValid($avatar)) {
            $avatar = Avatars::default();
        }
        $bio      = mb_substr(trim((string) ($input['bio'] ?? '')), 0, 280);
        $pronouns = trim((string) ($input['pronouns'] ?? '')) ?: null;
        $contact  = trim((string) ($input['contact'] ?? '')) ?: null;

        $this->users->saveProfile($userId, $avatar, $pronouns, $bio, $contact);

        return (new Response('', 302))->withHeader('Location', '/');
    }
}

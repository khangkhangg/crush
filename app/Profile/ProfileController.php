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
        private AvatarStore $avatarStore,
    ) {}

    public function edit(?int $userId): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        $user = $this->users->findById($userId);
        return Response::html($this->view->render('profile/edit', [
            'title'    => 'Your profile',
            'csrf'     => $this->csrf->token(),
            'user'     => $user,
            'avatars'  => Avatars::keys(),
            'returnTo' => '',
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
                'title'    => 'Your profile',
                'csrf'     => $this->csrf->token(),
                'user'     => $user,
                'avatars'  => Avatars::keys(),
                'returnTo' => '',
                'error'    => 'Your session expired. Please try again.',
            ]), 400);
        }

        $bio     = mb_substr(trim((string) ($input['bio'] ?? '')), 0, 280);
        $contact = trim((string) ($input['contact'] ?? '')) ?: null;

        $file = $input['_files']['avatar_file'] ?? ($_FILES['avatar_file'] ?? null);
        $uploaded = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && $this->avatarStore->store($userId, (string) $file['tmp_name']);

        $avatar = (string) ($input['avatar_key'] ?? '');
        if ($uploaded) {
            $avatar = 'custom';
        } elseif ($avatar === 'custom' && $this->avatarStore->has($userId)) {
            $avatar = 'custom';
        } elseif (!Avatars::isValid($avatar)) {
            $avatar = Avatars::default();
        }
        $this->users->saveProfile($userId, $avatar, null, $bio, $contact);

        $returnTo = (string) ($input['return_to'] ?? '');
        $dest = (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) ? $returnTo : '/';
        return (new Response('', 302))->withHeader('Location', $dest);
    }

    public function editPassword(?int $userId, ?string $error = null): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        return Response::html($this->view->render('profile/password', [
            'title' => 'Reset password',
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ]));
    }

    public function savePassword(?int $userId, array $input, string $csrf): Response
    {
        if ($userId === null) {
            return (new Response('', 302))->withHeader('Location', '/login');
        }
        if (!$this->csrf->validate($csrf)) {
            return $this->editPassword($userId, 'Your session expired. Please try again.')->withStatus(400);
        }
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');
        if (strlen($password) < 6) {
            return $this->editPassword($userId, 'Use at least 6 characters.')->withStatus(422);
        }
        if ($password !== $confirm) {
            return $this->editPassword($userId, 'Passwords do not match.')->withStatus(422);
        }
        $this->users->setPasswordHash($userId, password_hash($password, PASSWORD_DEFAULT));
        return (new Response('', 302))->withHeader('Location', '/profile');
    }
}

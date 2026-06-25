<?php
declare(strict_types=1);

use App\Admin\AdminAuthController;
use App\Admin\AdminController;
use App\Admin\BlockController;
use App\Auth\AuthController;
use App\Auth\GoogleController;
use App\Core\Response;
use App\Core\Router;
use App\Invite\InviteController;
use App\Landing\LandingController;
use App\Maps\MapsController;
use App\Profile\AvatarController;
use App\Profile\ProfileController;
use App\Respond\RespondController;
use App\Reveal\RevealController;

return static function (
    Router $router,
    AuthController $auth,
    GoogleController $google,
    InviteController $invite,
    callable $currentUserId,
    RespondController $respond,
    BlockController $block,
    AdminController $admin,
    ProfileController $profile,
    LandingController $landing,
    RevealController $reveal,
    AdminAuthController $adminAuth,
    MapsController $maps,
    AvatarController $avatar,
): void {
    $router->add('GET', '/health', static fn(): Response => Response::html('ok'));

    $router->add('GET',  '/login',              static fn(): Response => $auth->showLogin(
        (static fn($v) => is_string($v) ? $v : null)($_GET['e'] ?? null)
    ));
    $router->add('POST', '/login', static fn(): Response => $auth->loginPassword(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
        is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '',
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    ));
    $router->add('POST', '/login/magic', static fn(): Response => $auth->startMagic(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
        is_string($_POST['csrf']  ?? null) ? $_POST['csrf']  : ''
    ));
    $router->add('GET',  '/auth/magic/{token}', static fn(string $token): Response => $auth->completeMagic($token));
    $router->add('POST', '/logout',             static fn(): Response => $auth->logout(
        is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : ''
    ));

    $router->add('GET',  '/auth/google',          static fn(): Response => $google->redirect());
    $router->add('GET',  '/auth/google/callback', static fn(): Response => $google->callback(
        is_string($_GET['code']  ?? null) ? $_GET['code']  : null,
        is_string($_GET['state'] ?? null) ? $_GET['state'] : null
    ));

    // Front door
    $router->add('GET',  '/', static fn(): Response => $landing->home($currentUserId()));
    $router->add('POST', '/', static fn(): Response => $landing->start(
        $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    ));
    $router->add('GET', '/switch', static fn(): Response => $landing->switchAccount());
    // Dashboard moves to /invites only (the old `GET /` -> dashboard line is removed)
    $router->add('GET',  '/invites',       static fn(): Response => $invite->dashboard($currentUserId()));
    $router->add('GET',  '/invites/new',   static fn(): Response => $invite->showNew($currentUserId()));
    $router->add('POST', '/invites',       static fn(): Response => $invite->create($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/i/{token}/created', static fn(string $token): Response => $invite->showCreated($currentUserId(), $token));

    $router->add('GET', '/i/{token}/maps-preview', static fn(string $token): Response => $respond->mapsPreview(
        $token, is_string($_GET['url'] ?? null) ? $_GET['url'] : ''
    ));

    $router->add('GET',  '/i/{token}', static fn(string $token): Response => $respond->open($token));
    $router->add('POST', '/i/{token}', static fn(string $token): Response => $respond->submit(
        $token, $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
    ));

    $router->add('GET', '/unsubscribe/{token}', static fn(string $token): Response => $block->report($token));

    $router->add('GET',  '/admin/login', static fn(): Response => $adminAuth->showLogin());
    $router->add('POST', '/admin/login', static fn(): Response => $adminAuth->login(
        $_POST,
        (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    ));

    $router->add('GET',  '/admin',               static fn(): Response => $admin->dashboard($currentUserId()));
    $router->add('GET',  '/admin/settings',      static fn(): Response => $admin->settings($currentUserId()));
    $router->add('POST', '/admin/settings',      static fn(): Response => $admin->saveSettings($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('POST', '/admin/settings/test', static fn(): Response => $admin->sendTest($currentUserId(), (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/admin/themes',        static fn(): Response => $admin->themes($currentUserId()));
    $router->add('POST', '/admin/themes',        static fn(): Response => $admin->saveThemes($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/admin/moderation',    static fn(): Response => $admin->moderation($currentUserId(), (static fn($v) => is_string($v) ? $v : null)($_GET['q'] ?? null)));
    $router->add('POST', '/admin/block',         static fn(): Response => $admin->blockFromAdmin($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));

    $router->add('GET',  '/admin/templates',      static fn(): Response => $admin->templates($currentUserId()));
    $router->add('GET',  '/admin/templates/edit', static fn(): Response => $admin->editTemplate(
        $currentUserId(),
        (static fn($v) => is_string($v) ? $v : '')($_GET['key'] ?? ''),
        (static fn($v) => is_string($v) ? $v : '')($_GET['lang'] ?? '')
    ));
    $router->add('POST', '/admin/templates',      static fn(): Response => $admin->saveTemplate(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));

    $router->add('GET',  '/admin/share',      static fn(): Response => $admin->shareList($currentUserId()));
    $router->add('GET',  '/admin/share/edit', static fn(): Response => $admin->editShare(
        $currentUserId(), (static fn($v) => is_string($v) ? $v : '')($_GET['key'] ?? '')
    ));
    $router->add('POST', '/admin/share',      static fn(): Response => $admin->saveShare(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));
    $router->add('POST', '/admin/share/new', static fn(): Response => $admin->createShare(
        $currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')
    ));

    $router->add('GET',  '/profile', static fn(): Response => $profile->edit($currentUserId()));
    $router->add('POST', '/profile', static fn(): Response => $profile->save($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));

    $router->add('GET', '/invites/{token}/response', static fn(string $token): Response => $reveal->show($currentUserId(), $token));
    $router->add('GET', '/invites/{token}/calendar', static fn(string $token): Response => $reveal->downloadIcs($currentUserId(), $token));

    $router->add('GET', '/maps/preview', static fn(): Response => $maps->preview(
        $currentUserId(), is_string($_GET['url'] ?? null) ? $_GET['url'] : ''
    ));

    $router->add('GET', '/avatar/{id}', static fn(string $id): Response => $avatar->show((int) $id));

    $router->add('GET', '/lang/{code}', static fn(string $code): Response => (new \App\I18n\LangController())->set(
        $code, is_string($_SERVER['HTTP_REFERER'] ?? null) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : null
    ));
};

<?php
declare(strict_types=1);

use App\Admin\AdminController;
use App\Admin\BlockController;
use App\Auth\AuthController;
use App\Auth\GoogleController;
use App\Core\Response;
use App\Core\Router;
use App\Invite\InviteController;
use App\Respond\RespondController;

return static function (
    Router $router,
    AuthController $auth,
    GoogleController $google,
    InviteController $invite,
    callable $currentUserId,
    RespondController $respond,
    BlockController $block,
    AdminController $admin,
): void {
    $router->add('GET', '/health', static fn(): Response => Response::html('ok'));

    $router->add('GET',  '/login',              static fn(): Response => $auth->showLogin(
        (static fn($v) => is_string($v) ? $v : null)($_GET['e'] ?? null)
    ));
    $router->add('POST', '/login',              static fn(): Response => $auth->startMagic(
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

    $router->add('GET',  '/',              static fn(): Response => $invite->dashboard($currentUserId()));
    $router->add('GET',  '/invites',       static fn(): Response => $invite->dashboard($currentUserId()));
    $router->add('GET',  '/invites/new',   static fn(): Response => $invite->showNew($currentUserId()));
    $router->add('POST', '/invites',       static fn(): Response => $invite->create($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/i/{token}/created', static fn(string $token): Response => $invite->showCreated($currentUserId(), $token));

    $router->add('GET',  '/i/{token}', static fn(string $token): Response => $respond->open($token));
    $router->add('POST', '/i/{token}', static fn(string $token): Response => $respond->submit($token, $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));

    $router->add('GET', '/unsubscribe/{token}', static fn(string $token): Response => $block->report($token));

    $router->add('GET',  '/admin',               static fn(): Response => $admin->dashboard($currentUserId()));
    $router->add('GET',  '/admin/settings',      static fn(): Response => $admin->settings($currentUserId()));
    $router->add('POST', '/admin/settings',      static fn(): Response => $admin->saveSettings($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('POST', '/admin/settings/test', static fn(): Response => $admin->sendTest($currentUserId(), (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/admin/themes',        static fn(): Response => $admin->themes($currentUserId()));
    $router->add('POST', '/admin/themes',        static fn(): Response => $admin->saveThemes($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
    $router->add('GET',  '/admin/moderation',    static fn(): Response => $admin->moderation($currentUserId(), (static fn($v) => is_string($v) ? $v : null)($_GET['q'] ?? null)));
    $router->add('POST', '/admin/block',         static fn(): Response => $admin->blockFromAdmin($currentUserId(), $_POST, (static fn($v) => is_string($v) ? $v : '')($_POST['csrf'] ?? '')));
};

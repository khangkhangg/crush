<?php
declare(strict_types=1);

use App\Auth\AuthController;
use App\Auth\GoogleController;
use App\Core\Response;
use App\Core\Router;
use App\Invite\InviteController;

return static function (
    Router $router,
    AuthController $auth,
    GoogleController $google,
    InviteController $invite,
    callable $currentUserId
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
};

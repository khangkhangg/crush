<?php
declare(strict_types=1);

use App\Auth\AuthController;
use App\Auth\GoogleController;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router, AuthController $auth, GoogleController $google): void {
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
};

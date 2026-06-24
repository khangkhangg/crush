<?php
declare(strict_types=1);

use App\Auth\AuthController;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router, AuthController $auth): void {
    $router->add('GET', '/health', static fn(): Response => Response::html('ok'));

    $router->add('GET',  '/login',              static fn(): Response => $auth->showLogin());
    $router->add('POST', '/login',              static fn(): Response => $auth->startMagic($_POST['email'] ?? '', $_POST['csrf'] ?? ''));
    $router->add('GET',  '/auth/magic/{token}', static fn(string $token): Response => $auth->completeMagic($token));
    $router->add('POST', '/logout',             static fn(): Response => $auth->logout($_POST['csrf'] ?? ''));
};

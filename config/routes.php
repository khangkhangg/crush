<?php
declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->add('GET', '/health', static fn(): Response => Response::html('ok'));
};

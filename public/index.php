<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Response;
use App\Core\Router;

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$match = $router->match($method, $path);
$response = $match
    ? ($match['handler'])(...array_values($match['params']))
    : Response::html('<h1>Not found</h1>', 404);

if (!$response instanceof Response) {
    $response = Response::html((string) $response);
}
$response->send();

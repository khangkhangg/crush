<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Auth\AuthController;
use App\Auth\GoogleAuth;
use App\Auth\GoogleController;
use App\Auth\GoogleProvider;
use App\Auth\MagicLink;
use App\Auth\Session;
use App\Auth\UserRepo;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\PhpSessionStore;
use App\Core\Response;
use App\Core\Router;
use App\Core\SystemClock;
use App\Core\View;
use App\Invite\InviteController;
use App\Invite\InviteRepo;

/** @var Config $config */
$config = require dirname(__DIR__) . '/config/config.php';

$secure  = str_starts_with((string) $config->get('app_url', ''), 'https');
$store   = new PhpSessionStore($secure);
$session = new Session($store);
$csrf    = new Csrf($store);
$view    = new View(dirname(__DIR__) . '/templates');
$clock   = new SystemClock();
$pdo     = DB::connect($config);
$users   = new UserRepo($pdo, $clock);
$magic   = new MagicLink($pdo, $users, $clock);

$auth = new AuthController(
    $view, $session, $csrf, $magic,
    dirname(__DIR__) . '/storage/last-magic-link.txt',
    (string) $config->get('app_url', 'http://localhost'),
);

$googleClientId     = (string) $config->get('google_client_id', '');
$googleClientSecret = (string) $config->get('google_client_secret', '');
$googleRedirect     = (string) $config->get('google_redirect_uri', '')
    ?: rtrim((string) $config->get('app_url', 'http://localhost'), '/') . '/auth/google/callback';

$googleProvider = new GoogleProvider($googleClientId, $googleClientSecret, $googleRedirect);
$googleAuth     = new GoogleAuth($googleProvider, $users);
$googleCtrl     = new GoogleController($googleAuth, $session, $store, $googleClientId !== '');

$inviteRepo = new InviteRepo($pdo, $clock);
$inviteCtrl = new InviteController(
    $view, $csrf, $inviteRepo, $users, $clock,
    (string) $config->get('app_url', 'http://localhost')
);
$currentUserId = static fn(): ?int => $session->userId();

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router, $auth, $googleCtrl, $inviteCtrl, $currentUserId);

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

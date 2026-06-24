<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Admin\AdminController;
use App\Admin\BlockController;
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
use App\Ics\IcsBuilder;
use App\Invite\InviteController;
use App\Invite\InviteRepo;
use App\Landing\LandingController;
use App\Invite\ResponseRepo;
use App\Mail\MailerFactory;
use App\Mail\Postman;
use App\Maps\CurlFetcher;
use App\Maps\LinkResolver;
use App\Profile\ProfileController;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Reveal\RevealController;
use App\Security\BlockRepo;
use App\Security\RateLimiter;
use App\Settings\SettingsRepo;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;

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

$settings = new SettingsRepo($pdo);
$mailer   = MailerFactory::make($settings);
$postman  = new Postman($mailer, new IcsBuilder($clock), $view, (string) $config->get('app_url', 'http://localhost'));

$auth = new AuthController(
    $view, $session, $csrf, $magic,
    $mailer,
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
$blockRepo  = new BlockRepo($pdo, $clock);
$blockCtrl  = new BlockController($view, $inviteRepo, $blockRepo);
$inviteCtrl = new InviteController(
    $view, $csrf, $inviteRepo, $users, $clock,
    (string) $config->get('app_url', 'http://localhost'),
    $postman,
    new RateLimiter($pdo, $clock),
    $blockRepo,
);
$currentUserId = static fn(): ?int => $session->userId();

$responseRepo = new ResponseRepo($pdo, $clock);
$themeRepo    = new ThemeRepo($pdo);
$abEvents     = new AbEventRepo($pdo, $clock);
$assigner     = new ABAssigner($themeRepo, $inviteRepo);
$linkResolver = new LinkResolver(new CurlFetcher());
$crushOnboarder = new CrushOnboarder($users, $magic, $postman, (string) $config->get('app_url', 'http://localhost'));
$respondCtrl  = new RespondController(
    $view, $csrf, $inviteRepo, $responseRepo, $users, $assigner, $abEvents, $clock, $linkResolver, $postman, $crushOnboarder
);

$adminCtrl   = new AdminController($view, $csrf, $users, $settings, $themeRepo, $abEvents, $inviteRepo, $blockRepo, (string) $config->get('app_url', 'http://localhost'));
$profileCtrl = new ProfileController($view, $csrf, $users);
$landingCtrl = new LandingController($view, $csrf, $users, $magic, $session, $mailer, (string) $config->get('app_url', 'http://localhost'));
$revealCtrl  = new RevealController($view, $users, $inviteRepo, $responseRepo, new IcsBuilder($clock));

$router = new Router();
(require dirname(__DIR__) . '/config/routes.php')($router, $auth, $googleCtrl, $inviteCtrl, $currentUserId, $respondCtrl, $blockCtrl, $adminCtrl, $profileCtrl, $landingCtrl, $revealCtrl);

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

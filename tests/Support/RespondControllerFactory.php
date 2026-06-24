<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\EmailTemplateRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;

final class RespondControllerFactory
{
    public static function make(\PDO $pdo, FrozenClock $clock): RespondController
    {
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($pdo, $clock);
        $users = new UserRepo($pdo, $clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($clock), new EmailTemplateRepo($pdo), 'http://localhost');
        return new RespondController(
            $view,
            new Csrf(new ArrayStore()),
            $invites,
            new ResponseRepo($pdo, $clock),
            $users,
            new ABAssigner(new ThemeRepo($pdo), $invites, fn(int $m) => 0),
            new AbEventRepo($pdo, $clock),
            $clock,
            new LinkResolver(new FakeFetcher([])),
            $postman,
            new CrushOnboarder($users, new MagicLink($pdo, $users, $clock, 900), $postman, 'http://localhost'),
            new InvitePlaceRepo($pdo)
        );
    }
}

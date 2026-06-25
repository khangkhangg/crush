<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\MagicLink;
use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Ics\IcsBuilder;
use App\Invite\InvitePlaceRepo;
use App\Mail\EmailTemplateRepo;
use App\Invite\InviteRepo;
use App\Invite\ResponseRepo;
use App\Mail\Postman;
use App\Maps\LinkResolver;
use App\Respond\CrushOnboarder;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeFetcher;
use Tests\Support\FrozenClock;
use Tests\Support\SpyMailer;

final class RespondThemeTest extends DatabaseTestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    /** @param \Closure(int):int $picker chooses theme index 0,1,2 */
    private function controller(\Closure $picker): RespondController
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $users = new UserRepo($this->pdo(), $this->clock);
        $postman = new Postman(new SpyMailer(), new IcsBuilder($this->clock), new EmailTemplateRepo($this->pdo()), 'http://localhost');
        $onboarder = new CrushOnboarder($users, new MagicLink($this->pdo(), $users, $this->clock, 900), $postman, 'http://localhost');
        return new RespondController(
            $view, new Csrf(new ArrayStore()), $invites, new ResponseRepo($this->pdo(), $this->clock), $users,
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, $picker),
            new AbEventRepo($this->pdo(), $this->clock), $this->clock,
            new LinkResolver(new FakeFetcher([])), $postman, $onboarder, new InvitePlaceRepo($this->pdo())
        );
    }

    private function invite(bool $anon, string $email = 'sue@x.test'): array
    {
        $users = new UserRepo($this->pdo(), $this->clock);
        $sender = ($users->findByEmail($email) ?? $users->create($email, 'Sue', 'magic'))['id'];
        return (new InviteRepo($this->pdo(), $this->clock))->create([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => $anon ? 1 : 0, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => 'hi there', 'expires_at' => '2030-01-01 00:00:00',
        ]);
    }

    public function test_each_theme_renders_its_own_template_with_the_form(): void
    {
        // active themes ordered: bubblegum(0), love-letter(1), midnight(2)
        $cases = [0 => 'theme-bubblegum', 1 => 'theme-love-letter', 2 => 'theme-midnight'];
        foreach ($cases as $idx => $marker) {
            $ctrl = $this->controller(fn(int $m) => $idx);
            $res = $ctrl->open($this->invite(false, "sue{$idx}@x.test")['public_token']);
            $this->assertSame(200, $res->status());
            $this->assertStringContainsString($marker, $res->body(), "theme class for index $idx");
            $this->assertStringContainsString('name="chosen_date"', $res->body());
            $this->assertStringContainsString('name="chosen_time"', $res->body());
            $this->assertStringContainsString('name="meal_choice"', $res->body());
        }
    }

    public function test_anonymous_sender_hidden_in_theme(): void
    {
        $ctrl = $this->controller(fn(int $m) => 2); // midnight
        $res = $ctrl->open($this->invite(true)['public_token']);
        $this->assertStringContainsString('secret admirer', $res->body());
        $this->assertStringNotContainsString('sue@x.test', $res->body());
    }
}

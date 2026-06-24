<?php
declare(strict_types=1);

namespace Tests\Respond;

use App\Auth\UserRepo;
use App\Core\ArrayStore;
use App\Core\Csrf;
use App\Core\View;
use App\Invite\InviteRepo;
use App\Invite\InviteState;
use App\Invite\ResponseRepo;
use App\Respond\RespondController;
use App\Theme\AbEventRepo;
use App\Theme\ABAssigner;
use App\Theme\ThemeRepo;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FrozenClock;

final class RespondSubmitTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private Csrf $csrf;

    private function controller(): RespondController
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->csrf = new Csrf(new ArrayStore());
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $invites = new InviteRepo($this->pdo(), $this->clock);
        return new RespondController(
            $view, $this->csrf, $invites,
            new ResponseRepo($this->pdo(), $this->clock),
            new UserRepo($this->pdo(), $this->clock),
            new ABAssigner(new ThemeRepo($this->pdo()), $invites, fn(int $m) => 0),
            new AbEventRepo($this->pdo(), $this->clock),
            $this->clock
        );
    }

    private function makeInvite(array $over = []): array
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $sender = (new UserRepo($this->pdo(), $clock))->create('sue@x.test', 'Sue', 'magic')['id'];
        return (new InviteRepo($this->pdo(), $clock))->create(array_merge([
            'sender_id' => $sender, 'crush_email' => 'c@x.test', 'crush_name' => 'Cee',
            'is_anonymous' => true, 'reveal_on_response' => false, 'date_mode' => 'instant',
            'message' => null, 'expires_at' => '2026-02-01 00:00:00',
        ], $over));
    }

    public function test_bad_csrf_rejected(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $res = $ctrl->submit($invite['public_token'], ['chosen_start' => '2026-02-10T19:00'], 'wrong');
        $this->assertSame(400, $res->status());
    }

    public function test_missing_date_rejected(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite();
        $res = $ctrl->submit($invite['public_token'], ['meal_choice' => 'sushi'], $this->csrf->token());
        $this->assertSame(422, $res->status());
    }

    public function test_instant_submit_confirms_and_stores(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['date_mode' => 'instant', 'is_anonymous' => false]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
            'meal_wish' => 'sushi please', 'crush_contact' => '@cee', 'pickup_raw' => '1 Main St',
        ], $this->csrf->token());

        $this->assertSame(200, $res->status());
        $invites = new InviteRepo($this->pdo(), $this->clock);
        $reloaded = $invites->findByToken($invite['public_token']);
        $this->assertSame(InviteState::CONFIRMED, $reloaded['status']);

        $stored = (new ResponseRepo($this->pdo(), $this->clock))->findByInvite((int) $invite['id']);
        $this->assertSame('dinner', $stored['meal_choice']);
        $this->assertSame('1 Main St', $stored['pickup_raw']);
        // Not anonymous -> sender revealed.
        $this->assertStringContainsString('Sue', $res->body());
    }

    public function test_confirm_mode_goes_pending_and_keeps_secret(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['date_mode' => 'confirm', 'is_anonymous' => true, 'reveal_on_response' => false]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'coffee',
        ], $this->csrf->token());

        $reloaded = (new InviteRepo($this->pdo(), $this->clock))->findByToken($invite['public_token']);
        $this->assertSame(InviteState::PENDING_SENDER, $reloaded['status']);
        $this->assertStringNotContainsString('sue@x.test', $res->body());
    }

    public function test_anonymous_with_reveal_shows_sender(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['is_anonymous' => true, 'reveal_on_response' => true]);
        $res = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'coffee',
        ], $this->csrf->token());
        $this->assertStringContainsString('Sue', $res->body());
    }

    public function test_double_submit_returns_200_no_state_regression_no_duplicate_row(): void
    {
        $ctrl = $this->controller();
        $invite = $this->makeInvite(['date_mode' => 'instant', 'is_anonymous' => false]);

        // First submit — normal path, expect CONFIRMED.
        $res1 = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-10T19:00', 'meal_choice' => 'dinner',
        ], $this->csrf->token());
        $this->assertSame(200, $res1->status());

        $invites   = new InviteRepo($this->pdo(), $this->clock);
        $responses = new ResponseRepo($this->pdo(), $this->clock);

        $reloaded = $invites->findByToken($invite['public_token']);
        $this->assertSame(InviteState::CONFIRMED, $reloaded['status']);

        $firstResponse = $responses->findByInvite((int) $invite['id']);
        $this->assertNotNull($firstResponse);

        // Second submit — same token, same valid data + valid csrf.
        // Must return 200 (not 500), must NOT regression the state, must NOT create a second row.
        $res2 = $ctrl->submit($invite['public_token'], [
            'chosen_start' => '2026-02-15T20:00', 'meal_choice' => 'coffee',
        ], $this->csrf->token());

        $this->assertSame(200, $res2->status());

        $reloadedAgain = $invites->findByToken($invite['public_token']);
        $this->assertSame(InviteState::CONFIRMED, $reloadedAgain['status'], 'Status must not regress after double-submit');

        // Only one response row must exist (findByInvite returns the original row).
        $storedAgain = $responses->findByInvite((int) $invite['id']);
        $this->assertSame($firstResponse['id'], $storedAgain['id'], 'No duplicate response row must be created');
        $this->assertSame($firstResponse['chosen_start'], $storedAgain['chosen_start'], 'Original answer must be preserved');
    }
}

# Crush v4 — Sharing, Cuisine Tags & Responsive Form (Design)

**Date:** 2026-06-25
**Status:** Approved (decisions captured 2026-06-25)

## Goal

Four improvements to the invite-creation experience:
1. Fix the form layout (overflow bug) and make it responsive for desktop + mobile.
2. Add an optional **cuisine tag** per suggested spot, and show the crush **only the vibes the sender curated** (collapsing to a cuisine-led view when there is exactly one).
3. Let the sender **choose delivery per invite** — email it to the crush, or share the link themselves (email optional).
4. A **share screen** with admin-configurable social/messaging buttons.

## Decisions (locked)

- **Meal model:** Keep the 6 meal-time vibes (coffee/brunch/lunch/dinner/dessert/drinks). Each suggested spot gains an optional cuisine tag. The crush sees only vibes the sender suggested a spot for; if exactly one, the chooser collapses and the cuisine leads.
- **Delivery:** Per-invite choice — "Email it to them" (email required, email sent) or "I'll share the link myself" (email optional, no email sent). Both end on the share screen.
- **Share apps:** Always-present Copy link + native share sheet; admin-toggleable external targets seeded with WhatsApp, Telegram, Messenger, SMS, X, Line. Implemented as stateless web-intent URL templates (`{url}` / `{text}` placeholders) — no API keys/OAuth.
- **Constraints:** PHP 8.1+, PDO MySQL, PHPUnit 10, no new Composer deps. **Icons only — never emojis** (brand glyphs added to the SVG sprite). All output `$e()`-escaped; CSRF on POST; admin-gated admin screens.

## Current state (baseline)

- `templates/invite/new.php` — all-inline-styled form; place grid is a flex row (`72px` label + two `flex:1` inputs) with **no `box-sizing:border-box`** and flex items at default `min-width:auto` → the right column overflows the `width:min(92vw,380px)` card. `templates/layout.php` has no global box reset.
- `MealOptions::CHOICES` (app/Respond/MealOptions.php) — the 6 vibes. The crush page (`respond/themes/*` → `respond/_form.php`) always shows **all 6** chips regardless of what the sender curated; suggested spots reveal on pick.
- `invite_places` — one row per (invite, meal_key): name, url, resolved name/address/clean_url. No cuisine.
- `invites.crush_email` is `NOT NULL` + always required; `InviteController::create` always `sendInvite()` and redirects to `/i/{token}/created` → `invite/created.php` (a plain copyable link, no share buttons).
- Icon sprite: `templates/partials/icons.php` — `<symbol id="ic-…">` with `fill/stroke="currentColor"`.

---

## Architecture

### A. Layout / responsive (Plan v4-1)

- Add a global `*,*::before,*::after { box-sizing:border-box }` reset to `templates/layout.php`.
- Add an optional **wide card** variant: `.card` stays `min(92vw,380px)`; a `$cardClass` template var lets a page request `.card--wide` (`width:min(94vw,640px)`). The invite form opts in; landing stays narrow.
- Rework the place grid into a responsive CSS grid (scoped `<style>` in `new.php`): desktop = `label | name | cuisine | maps-url`; mobile (`max-width:560px`) = stacked, label as a row header. Inputs get `min-width:0` so they shrink.

### B. Cuisine tags + curated/collapsing vibes (Plan v4-1)

- Migration `0011_place_cuisine.sql`: `ALTER TABLE invite_places ADD COLUMN cuisine VARCHAR(40) NULL;`
- `InvitePlaceRepo::add` gains a `?string $cuisine` parameter (stored/upserted); `forInvite`/`forMeal` already `SELECT *` so they return it.
- Sender form: each vibe row gains a `places[{key}][cuisine]` text input with a shared `<datalist>` of common cuisines (Italian, Japanese, Korean, Vietnamese, Thai, Chinese, Mexican, Indian, American, French, Mediterranean, BBQ, Vegan, Dessert).
- `InviteController::create` reads `places[{key}][cuisine]` and passes it to `add`.
- **Crush page logic** (in `renderInvite` + `respond/_form.php`): compute `visibleMeals` = vibes with a place row; if empty → all 6 (zero-setup fallback). Pass `visibleMeals` + `places` to the form.
  - `count(visibleMeals) >= 2`: show those chips; each reveals "Label · Cuisine at SpotName" on pick (extend the existing data-attr JS to include cuisine).
  - `count(visibleMeals) == 1`: collapse — no chips; render a hidden `meal_choice` = that vibe, and a prominent line "Label · Cuisine at SpotName" (cuisine emphasized). The rest of the form (time/wish/contact/pickup) is unchanged.
  - `count(visibleMeals) == 6` with no places (fallback): unchanged current behavior.

### C. Delivery choice (Plan v4-2)

- Migration `0012_invite_email_nullable.sql`: `ALTER TABLE invites MODIFY crush_email VARCHAR(191) NULL;`
- Sender form: a `delivery` radio — `email` (default, shows the email field, required) vs `link` (email field optional/dimmed). Progressive-enhanced with a tiny script; server is the source of truth.
- `InviteController::create`:
  - `$delivery = ($input['delivery'] ?? 'email') === 'link' ? 'link' : 'email';`
  - `email` mode: keep the existing required+valid check (422), the per-email + per-sender rate caps, the block check, and `sendInvite`.
  - `link` mode: email optional. If provided and invalid → 422. Per-email cap + block check only when an email is present; per-sender cap always. **No** `sendInvite`.
  - Both modes still create the invite and redirect to the share screen.
- `InviteRepo::create` already stores `crush_email` as given; pass `null` when blank.

### D. Share targets + share screen + admin (Plan v4-2)

- Migration `0013_share_targets.sql`: table `share_targets(id, `key` UNIQUE, label, icon, url_template, sort INT, enabled TINYINT)` seeded with whatsapp/telegram/messenger/sms/x/line (sensible `sort`, all enabled). `url_template` holds e.g. `https://wa.me/?text={url}`.
- `app/Share/ShareTargetRepo.php`: `listEnabled(): array` (enabled, ordered by sort), `all(): array`, `getExact(string $key): ?array`, `update(key,label,url_template,enabled): void`, `setEnabled(key,bool): void`. `render(string $template, string $url): string` interpolates `{url}` (URL-encoded) and `{text}`.
- Icon sprite: add monochrome brand `<symbol>`s — `ic-whatsapp`, `ic-telegram`, `ic-messenger`, `ic-sms`, `ic-x`, `ic-line`, `ic-copy`, `ic-share`.
- `invite/created.php` → share screen: the private link (read-only, click-to-select), a **Copy** button (clipboard API), a **Share** button (`navigator.share`, shown only when available), and one `<a>` per enabled share target with the icon + label and the interpolated href. Recipient context line reflects delivery mode ("Send this to {name}" vs "Share your invite link"). `InviteController::showCreated` passes `shareTargets` (rendered hrefs) + `link`.
- Admin `/admin/share`: list targets with an enable toggle + edit (label, url_template); add new. Mirrors the email-templates admin (admin-gated, CSRF, `$e()`). `AdminController` gains a trailing `ShareTargetRepo` dep; routes `GET /admin/share`, `GET /admin/share/edit`, `POST /admin/share`.

## Data flow

1. Sender fills the form (delivery mode, vibes + cuisine + maps links) → `POST /invites`.
2. `create` validates per mode, stores invite (+places with cuisine), conditionally emails, redirects to `/i/{token}/created`.
3. Share screen interpolates the invite URL into each enabled target's `url_template`; sender taps a target (opens the app's web-intent) or copies/native-shares.
4. Crush opens `/i/{token}` → sees only curated vibes (collapsed if one) with cuisine, picks, responds (unchanged downstream).

## Error handling

- Mail failures stay swallowed + logged (`Postman`/`dispatch`).
- `LinkResolver` SSRF guard unchanged (cuisine is plain text, not fetched).
- Share `url_template` is admin-authored; the invite URL is URL-encoded before interpolation; rendered hrefs are `$e()`-escaped in the attribute. Only `http(s)`/`sms:`/`mailto:` schemes allowed for targets (validated on save, like `Postman::safeHref` but allowing `sms`/`mailto`).

## Testing

- MySQL `crush_test` for integration (repos, controllers, admin). Pure logic (share render, visible-vibes computation) unit-tested.
- Key cases: place cuisine round-trips; visible-vibes = curated set, collapse at 1, fallback at 0; delivery=link skips email + allows blank email; delivery=email still required; share render encodes the URL + rejects `javascript:`; admin/share gated + CSRF.

## Phasing

- **Plan v4-1:** layout/responsive fix + cuisine tags + curated/collapsing vibes. Ship + deploy.
- **Plan v4-2:** delivery choice + share targets table/repo + share screen + admin/share. Ship + deploy.

# Crush v3 — Auth + Email Templates + i18n Spec

**Date:** 2026-06-24
**Status:** Approved (ready for implementation planning)
**Builds on:** feature-complete v1 + the v2 redesign.

## 1. Summary

Three coupled improvements driven by the live deployment: (A) the landing never dead-ends — every entry continues into invite setup, with an identity banner for returning emails; (B) a dedicated **password** admin login at `/admin/login` (the app is otherwise magic-link only); (C) **language detection** (English/Vietnamese/Korean) that picks each user's default language; and (D) **admin-managed, localized email templates** for all four transactional emails.

Audience 15–26; icons only, never emojis. Production: `https://crush.didudi.com`.

## 2. Decisions (locked during brainstorming)

| Topic | Decision |
| --- | --- |
| Landing — new email | Create account (detected lang) → log in → send welcome email → `/invites/new`. |
| Landing — existing email | Log in → **no** welcome email → `/invites/new` with an identity banner ("Creating as <avatar> <name> — not you? use a different email / log in"). Invite + responses attribute to that account. Banner is the typo guard. |
| Security tradeoff | Existing-email auto-login is accepted (low-stakes app); the banner mitigates mistyped emails. |
| Admin login | Dedicated `/admin/login` (email + password), admin-only. `users.password_hash`. Set `dkhang@gmail.com` / `Sushi08!` + `is_admin`. Regular users stay magic-link. |
| Languages | English (default), Vietnamese, Korean. Detected from `Accept-Language`, stored as the user's default. |
| Email templates | `email_templates(key, lang, subject, body_html)` for keys welcome/invite/result/magic; admin-editable per language; rendered with `{{placeholder}}` interpolation (user values escaped). |

## 3. Landing flow (no dead-end)

`LandingController::start(name, email, csrf, acceptLanguage)`:
- **New email:** `UserRepo::create(email, name, 'magic', lang: detected)` → `Session::login` → send the **welcome** template (recipient lang) → `302 /invites/new`.
- **Existing email:** `Session::login(existing.id)` → **no** welcome email → `302 /invites/new`.
- `/invites/new` shows an identity banner when the session user has a completed profile or a known name/avatar: "Creating as {avatar} {name} — not you? **use a different email** (logout → landing) · **log in**". The banner is always safe (it only ever reflects the current session user).
- The "check your email" state is removed. (A magic-link email is still sent on the dedicated returning-login path and `/login`.)

## 4. Admin password login

- `users.password_hash VARCHAR(255) NULL` (bcrypt via `password_hash`).
- `AdminAuthController`: `GET /admin/login` (email + password form, CSRF), `POST /admin/login` → look up user by email, `password_verify`, require `is_admin === 1` → `Session::login` → `302 /admin`. Generic "invalid credentials" on any failure (no user-enumeration). Rate-limited via the existing `RateLimiter` (per-IP + per-email).
- A `bin/set-password.php <email> <password>` CLI sets `password_hash` (used to provision `dkhang@gmail.com` = `Sushi08!`).
- Admin URL: `https://crush.didudi.com/admin/login`.

## 5. Language detection + i18n

- `App\Core\Locale`: `const SUPPORTED = ['en','vi','ko']`, `detect(?string $acceptLanguage): string` (best q-weighted match, fallback `en`), `isSupported(string): bool`.
- `users.lang VARCHAR(5) NULL`, `invites.lang VARCHAR(5) NULL`.
- Set `users.lang` at account creation from the detected language (landing for senders; respond-submit for crushes).
- Email language selection:
  - **welcome / magic / result** → recipient user's `lang` (fallback en).
  - **invite** (crush has no account yet) → the **invite's** `lang` = the sender's lang at creation (stored on the invite).

## 6. Email templates

- `email_templates` table: `id, key VARCHAR(32), lang VARCHAR(5), subject VARCHAR(255), body_html TEXT`, `UNIQUE(key, lang)`. Seeded EN/VI/KO for welcome/invite/result/magic.
- `App\Mail\EmailTemplateRepo`: `get(key, lang): ?array` (exact, else `(key,'en')`), `render(key, lang, vars): array{subject,html}` — replaces `{{name}}` style tokens; **escapes every value** with `htmlspecialchars` before substitution (templates are admin-authored HTML; user-supplied values must not inject markup). URL values are system-generated/`safeHref`-validated.
- `Postman` rewritten to resolve `(key, lang, vars)` via the repo instead of rendering hardcoded PHP views. `sendWelcome/sendInvite/sendResult` + the magic-link email all go through templates. Mail failures still swallowed + logged.
- **Placeholders:** welcome `{{name}} {{link}}`; invite `{{senderLabel}} {{message}} {{link}} {{unsubscribe}}`; result `{{crushName}} {{when}} {{meal}} {{place}} {{mapHref}}`; magic `{{link}}`.
- **Admin UI** `/admin/templates`: list all key×lang rows; edit `subject` + `body_html` per row (CSRF, admin-gated). A small "available placeholders" hint per key.

## 7. Data model changes

- `users`: `+ password_hash VARCHAR(255) NULL`, `+ lang VARCHAR(5) NULL`.
- `invites`: `+ lang VARCHAR(5) NULL`.
- New `email_templates(key, lang, subject, body_html)` with `UNIQUE(key, lang)`, seeded.

## 8. Phasing (each shippable; subagent-driven TDD)

1. **Admin password login** — `users.password_hash`, `AdminAuthController` + `/admin/login`, `bin/set-password.php`. *Deploy first to unblock admin access.*
2. **Locale** — `App\Core\Locale`, `users.lang` + `invites.lang` columns, set at creation.
3. **Email templates core** — `email_templates` table + seeds, `EmailTemplateRepo`, `Postman` rewrite (lang-aware).
4. **Admin templates UI** — `/admin/templates` list + edit.
5. **Landing flow** — new-vs-existing (welcome only for new), identity banner on `/invites/new`, wire lang detection into landing + respond-submit.

## 9. Security

- Existing-email auto-login is an accepted product tradeoff (§2); the identity banner is the mitigation. No change to magic-link or crush-no-auth-to-respond.
- Admin password: bcrypt (`password_hash`/`password_verify`), `/admin/login` rate-limited, generic errors, `is_admin` required. Passwords only for admins.
- Template rendering escapes all interpolated user values; admin-authored HTML bodies are trusted (admin-gated editing). URL placeholders pass through `Postman::safeHref` where applicable.
- CSRF on all new POSTs (`/admin/login`, `/admin/templates`).

## 10. Out of scope

Per-language UI/theme copy (only emails localized), passwords for non-admin users, RTL languages, self-serve language switcher (language is auto-detected; admin can add template languages).

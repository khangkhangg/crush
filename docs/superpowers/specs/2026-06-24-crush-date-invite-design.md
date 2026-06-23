# Crush — Date Invite App · Design Spec

**Date:** 2026-06-24
**Status:** Approved (ready for implementation planning)

## 1. Summary

Crush is a lightweight PHP/MySQL web app. A signed-in **sender** emails someone they
have a crush on a beautiful, romantic invite. The **crush** (no signup required) opens
a secure link and picks a date & time, a meal vibe, their contact info, and a pickup
location (address or Google Maps link). The sender receives an email back with all the
choices plus a calendar (`.ics`) attachment to add to their phone and set a reminder.

Audience: 15–26. Tone: romantic, cute, premium. **Icons only — never emojis** (project rule).

Success criteria (v1): the experience feels magical **and** reliably puts a real date on
both calendars with reminders.

## 2. Core decisions (locked during brainstorming)

| Topic | Decision |
| --- | --- |
| Stack | Lean vanilla PHP + PDO/MySQL, PHP templates, minimal Composer deps |
| Sender auth | Required. Magic link (passwordless) **or** Google OAuth |
| Crush auth | **Not** required to respond; optional account after responding |
| Anonymity | Per-invite: sender chooses "reveal now" or "stay anonymous"; optional reveal-on-response |
| Date mode | Per-invite: **instant** (crush's pick is final) or **confirm** (sender approves first) |
| Meal prefs | Single cute icon picker ("what are you craving?") + free-text wish |
| Maps extraction | Resolve short links + parse place **name + address**, emit one clean link. No paid API |
| Email delivery | Pluggable driver chosen in admin: Resend (API) / SMTP (PHPMailer) / `mail()` |
| Themes | 3 designs — Love Letter, Bubblegum Cutecore, Midnight Crush — admin A/B tested |
| Polish | make-interfaces-feel-better principles across all themes |

## 3. Architecture (vanilla PHP, Option A)

```
/public            web root (only this is exposed)
  index.php        front controller / router entry
  /assets          css, js, icon sprite, per-theme stylesheets
/app
  /Core            Router, Request, Response, DB (PDO), Session, Csrf, View, RateLimiter
  /Auth            MagicLink, GoogleOAuth, UserRepo
  /Invite          InviteController, InviteRepo, StateMachine
  /Respond         public crush-facing flow (no auth)
  /Mail            MailerInterface + ResendMailer, SmtpMailer, PhpMailMailer, MailerFactory
  /Maps            LinkResolver (short-link unfurl + place parse, SSRF-guarded)
  /Ics             IcsBuilder
  /Theme           ThemeManager + ABAssigner
  /Admin           AdminController (settings, mailer config, themes, moderation)
/templates         PHP view templates (layouts, 3 themes, emails)
/config            config.php (secrets via env), routes
/migrations        numbered .sql files
/storage           logs, rate-limit cache
```

Composer dependencies (minimal): `phpmailer/phpmailer`, `google/apiclient`. Everything else
hand-rolled. Sessions for auth; PDO prepared statements everywhere; CSRF on all POSTs;
output escaping in all templates.

## 4. Data model (MySQL)

- **users** — `id, email, name, auth_provider (magic|google), google_id, avatar_url, created_at, is_admin`
- **magic_tokens** — `id, user_id, token_hash, expires_at, used_at` (15-min, single-use)
- **invites** — `id, public_token, sender_id, crush_email, crush_name, is_anonymous, reveal_on_response, date_mode (instant|confirm), status, theme_key, message, created_at, expires_at`
- **invite_date_options** — candidate slots for confirm mode; also stores chosen `start_at`/`end_at`
- **responses** — `id, invite_id, chosen_start, chosen_end, meal_choice, meal_wish, crush_contact, pickup_raw, pickup_name, pickup_address, pickup_clean_url, created_at`
- **settings** — `key, value` (active mailer driver + creds, Google keys, from-address, flags)
- **themes** — `key, name, is_active, weight`
- **ab_events** — `id, invite_id, theme_key, event (opened|started|completed), created_at`
- **rate_limits** — `id, scope, identifier, window_start, count`
- **blocks** — `id, sender_id, crush_email, reason, created_at` (report/block)

## 5. Invite lifecycle (state machine)

```
draft → sent → opened → responded ─┬─ (instant) → confirmed → closed
                                    └─ (confirm) → pending_sender → confirmed | declined → closed
extra states: expired, blocked
```

- **instant:** crush's pick is final → `.ics` issued immediately to both parties.
- **confirm:** crush's pick is a request → sender gets an approve/decline email →
  approve issues `.ics`; decline lets sender re-propose.
- `is_anonymous`, `reveal_on_response`, and `date_mode` are per-invite, set at creation.

## 6. Sender flow (signed in)

1. Sign in (magic link or Google).
2. Create invite: crush email + name, short message, date mode (+ optional proposed slots),
   anonymity toggle (+ reveal-on-response), optional forced theme (else A/B).
3. App emails the crush a themed invite with the secure link.
4. Dashboard lists sent invites with live status.

## 7. Crush flow (no signup)

1. Open tokenized link → themed "tap to open" reveal (the polished moment).
2. Pick date & time (free calendar, or from sender's proposed slots).
3. Pick meal vibe (icon picker) + free-text wish.
4. Enter contact info + pickup location (address or Maps link → auto-unfurled).
5. Submit → confirmation screen; identity revealed here iff sender chose reveal-on-response;
   optional "make an account to save this."

## 8. Anonymity (per-invite)

Sender chooses at creation: reveal now (name shown in invite) or stay anonymous
("a secret admirer" throughout). If anonymous + reveal-on-response, identity is shown on the
crush's confirmation screen after they respond. Sender email is never exposed in page source
or links; all rendering is server-side from `sender_id`.

## 9. Google Maps link extraction (`/Maps/LinkResolver`)

Input: plain address or any Maps URL (incl. `maps.app.goo.gl`). Steps:
1. Unfurl short links by following redirects server-side (cURL, capped redirects, timeout).
2. Parse resolved URL + page `<title>`/OpenGraph meta for place **name** + **address**.
3. Emit one clean canonical link: `https://www.google.com/maps/search/?api=1&query=<name+address>`.
4. Store `pickup_name`, `pickup_address`, `pickup_clean_url`; embed in email + `.ics LOCATION`.
5. Fallbacks: on parse failure keep raw link + typed address.
6. **SSRF guard:** google-domain allow-list, block internal/private IPs, hard timeouts.

## 10. Email + `.ics` pipeline

- **Pluggable mailer (Strategy):** `MailerInterface::send($to, $subject, $html, $attachments)`.
  `MailerFactory` reads active driver + creds from `settings`. Drivers: Resend (API),
  SMTP (PHPMailer), `mail()`.
- **Two transactional emails:** invite → crush; result (with `.ics`) → sender.
- **`.ics` (`IcsBuilder`):** RFC 5545 `VEVENT` — title ("Date with <name>"), `DTSTART/DTEND`,
  `LOCATION`, `DESCRIPTION` (meal vibe + wish + contact), `VALARM` reminder; `text/calendar`
  attachment so phones offer "Add to Calendar."
- All emails icon-based (no emojis), themed to match the invite.

## 11. Three themes + A/B testing

- Themes share one layout contract + icon sprite + motion layer; each has its own stylesheet
  and personality-tuned open animation.
- Assignment: if sender doesn't force a theme, `ABAssigner` picks weighted by `themes.weight`
  and pins `theme_key` to the invite for consistency.
- Tracking: `ab_events` logs `opened → started → completed`; admin sees per-theme conversion.

## 12. Admin / control panel (`is_admin`)

- Mailer config (driver, creds, send test email).
- Settings (Google OAuth keys, from-name/address, invite expiry, flags).
- Themes (toggle active, set A/B weights, view conversion funnel).
- Moderation (list/search invites, block abusive senders, view reports, take down content).

## 13. Auth

- **Magic link:** email → single-use 15-min hashed token → session. Reuses the mailer.
- **Google OAuth:** `google/apiclient`, standard code flow.
- Crushes never forced to auth; optional post-response account merges by email.

## 14. Security & abuse

This app emails strangers, so abuse handling is first-class:
- Unguessable `public_token`; CSRF on all POSTs; prepared statements; template escaping.
- Rate limits: per-sender invites/day, per-IP, per crush-email (no spamming one person).
- Every crush email has one-click **block/report** that halts further invites sender→address.
- SSRF guard on Maps fetches.
- Secrets via environment, never committed. Hardened sessions + magic tokens
  (httponly, samesite, secure).

## 15. Feel & motion layer (all themes)

make-interfaces-feel-better principles: staggered reveal on invite-open, `scale(0.96)` press
feedback, layered shadows over borders, concentric border radii, specific (non-`all`)
transitions, `text-wrap: balance` headings, 44px hit areas, `tabular-nums` on counters.
Keyframes for one-shot sequences; CSS transitions for interruptible interactive states.

## 16. Out of scope for v1 (YAGNI)

SMS, in-app chat, native apps, payments, multi-date/group invites, social feed, gift-sending.
The funnel is: sign in → send → crush responds → calendar email. Nothing more.

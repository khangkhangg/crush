# Crush v2 — Redesign Spec

**Date:** 2026-06-24
**Status:** Approved (ready for implementation planning)
**Builds on:** the feature-complete v1 (`docs/superpowers/specs/2026-06-24-crush-date-invite-design.md`).

## 1. Summary

A v2 redesign of Crush that adds a cute one-screen **landing page** as the front door, switches the flow to **two accounts** (sender and crush both get accounts) with a **profile step**, **gates the crush's response reveal behind the sender completing their profile**, lets the sender attach **a restaurant to each meal vibe**, and rebuilds the **three themes as structurally distinct designs** (not recolors). Auth stays **passwordless (magic-link only)**.

Audience 15–26; tone romantic/cute/premium. **Icons only — never emojis.** Local dev serves on **port 8888**.

## 2. Decisions (locked during brainstorming)

| Topic | Decision |
| --- | --- |
| Auth | Magic-link only, no passwords. "Change password" → "complete your profile." |
| New vs returning on landing | New email → create account + **log in immediately** + email a magic link for later. Existing email → email a login link only (never silent-login an existing account). |
| Crush account | Auto-created on submit; welcome email + same complete-profile flow. Crush still needs **no account to respond**. |
| Reveal gating | Crush's full response + calendar are **locked until the sender completes their profile**. A teaser shows as soon as the crush answers. |
| Place options | **One restaurant per meal vibe**, optional, set at invite creation; revealed to the crush when they pick that vibe. |
| Themes | Three **structurally different** designs (envelope/letter, scrapbook, dating-app card) — not just palettes. Icons-only + motion layer retained. |
| Local port | `8888`. |

## 3. End-to-end flow

```
Landing (name + email)
  ├─ new email  → create user + session + email magic link → Create Invite
  └─ existing   → email login link → "check your email"

Create Invite (date mode, anonymity, meal vibes + optional restaurant per vibe)
  → prompt "Complete your profile"
  → Dashboard (status: waiting)

Crush opens secure link → themed invite → pick date + meal vibe
  → matched restaurant revealed inline → contact + submit
  → create/find crush user by email + stamp + welcome email
  → themed thank-you

Sender dashboard → "your crush answered" teaser (LOCKED)
  → finish profile → UNLOCK → Confirmation: full response + .ics download/add-to-calendar
```

## 4. Auth, accounts, profiles

- **Landing controller** replaces the bare `/login` as the front door (`/login` still exists for the magic-link complete step). Entering name+email:
  - **New email:** `UserRepo::create(email, name, 'magic')`, set the session (immediate login), email a magic link (for returning), redirect to `/invites/new`.
  - **Existing email:** issue a magic link via `MagicLink::start`, render "check your email" — do **not** set the session. (Prevents account takeover by typing someone's email.)
- **Crush account:** in `RespondController::submit`, after storing the response, `UserRepo::findByEmail($crushEmail) ?? create(...)`; send a welcome email (magic link) and route the crush into complete-profile on first login. Responding itself stays unauthenticated.
- **Profile** (`ProfileController`): fields — `display name` (prefilled), **avatar pick** from a fixed SVG set (`avatar_key`), `pronouns` (optional), `bio` (one line), `contact` (optional). Saving stamps `profile_completed_at`.
- **Reveal gate:** the confirmation page checks `sender.profile_completed_at IS NOT NULL`. If null, render the locked teaser with a "complete your profile" CTA.

## 5. Landing page (one screen, cute)

Full-viewport, centered, no scroll. Gradient background with slowly drifting heart/sparkle SVG icons and a bobbing sealed-envelope mascot. Big playful "Crush" wordmark + beating-heart icon, one-line tagline, and a single rounded input row: **name · email · "Start" button**. CSRF-protected POST to the landing controller. Mobile-first; everything above the fold at common phone + desktop sizes.

## 6. Place options (one restaurant per meal vibe)

- At invite creation, beneath each meal vibe the sender offers, an optional **"add a spot"** input: restaurant `name` + optional Maps `url`.
- New `invite_places` table: `id, invite_id, meal_key, place_name, place_url, place_resolved_name, place_resolved_address, place_clean_url`. On save, the Maps `url` is run through the existing `LinkResolver` (SSRF-guarded) to populate the resolved fields.
- On the crush's invite page, picking a meal vibe reveals its matched spot inline ("Dinner at **Tartine**" + a clean Maps link). The chosen vibe's place is copied onto the `responses` row's `pickup_*` fields so the sender's confirmation + `.ics LOCATION` use it. If the crush also types their own pickup, that still takes precedence (existing behavior).

## 7. Three structurally distinct themes

Each theme shares the data + the icons-only rule + the motion principles, but has its **own template and layout**, not just CSS variables:

1. **Love Letter — envelope + letter (skeuomorphic).** A wax-sealed envelope the crush taps; it unfolds into a handwritten-style letter on aged paper. Form fields are drawn as lines on stationery. Vertical, intimate, paper texture. Open animation: seal cracks, flap lifts.
2. **Bubblegum — scrapbook page.** Tilted polaroid frames, washi-tape strips, sticker meal-icons, doodle arrows, chunky rotated elements. Chaotic-cute Y2K. Fields look like taped-on notes.
3. **Midnight — dating-app match card.** Dark full-bleed hero, glassmorphic stacked card, starfield, meal options as a horizontal pill carousel, glowing CTA. Premium mobile-app feel.

Theme A/B assignment, funnel events, and the anonymity/reveal logic are unchanged. The respond flow renders a per-theme template (`templates/respond/themes/{key}.php`) instead of one shared `show.php`.

## 8. Data model changes

- `users`: add `avatar_key VARCHAR(32) NULL`, `pronouns VARCHAR(32) NULL`, `bio VARCHAR(280) NULL`, `contact VARCHAR(191) NULL`, `profile_completed_at DATETIME NULL`.
- New `invite_places`: as in §6.
- No new column needed for reveal gating (derived from sender's `profile_completed_at`).
- Crush-account link is implicit: `invites.crush_email` ↔ `users.email` after submit.

## 9. Reused vs. changed

**Reused unchanged:** Core, `MailerFactory`/`Postman`/`IcsBuilder`, `LinkResolver`/SSRF, `ABAssigner`/`ThemeRepo`/`AbEventRepo`, `RateLimiter`/`BlockRepo`, admin panel.
**Changed/new:** `LandingController` + landing page; `ProfileController` + profile pages + `users` columns; `RespondController` (place reveal + crush-account creation); `InviteController` (place options on create); confirmation page (reveal gate); three rebuilt theme templates; `Postman` (welcome email + profile prompts). Dev serve port → 8888.

## 10. Out of scope (this iteration)

Photo upload, password auth, in-app chat, editing/withdrawing a sent invite, multiple crushes per invite, place options with multiple restaurants per vibe.

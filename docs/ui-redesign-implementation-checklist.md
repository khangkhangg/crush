# Crush UI Redesign Implementation Checklist

Scope: landing, login, invite builder, invite-created share screen, recipient invite/acceptance, confirmation, invites dashboard, and reveal/profile gate.

## Core UX

- [x] Landing has a generated mascot/hero asset, clear start form, and desktop preview art.
- [x] Login has generated romantic artwork, clear sign-in hierarchy, magic-link path, and mobile-friendly spacing.
- [x] Invite builder has guided steps, gamified progress, live preview art, smooth toggles, and no horizontal overflow.
- [x] Hotel vibe shows hotel-only fields: hotel name and optional map link; no cuisine pills, remove button, or add another place.
- [x] Recipient invite uses broad vibe wording, not food-only wording, because Hotel is an option.
- [x] Share screen has generated invite art, copy/share feedback, and next-step guidance.
- [x] Confirmation page feels like an emotional success moment with generated success art.
- [x] Dashboard uses status/timeline visuals and richer empty/card states.
- [x] Reveal/profile gate has richer art and keeps the profile requirement understandable.
- [x] Vietnamese copy keeps the personal Gen-Z tone, especially `người ấy` phrasing.

## Visual Assets

- [x] Generate transparent mascot envelope art.
- [x] Generate transparent invite envelope art.
- [x] Generate transparent success envelope art.
- [x] Generate transparent vibe sticker sheet with Hotel.
- [x] Wire generated assets into all target pages.
- [x] Keep generated image usage responsive and non-blocking (`loading`, `decoding`, stable dimensions).

## Motion And Polish

- [x] Split/stagger main page entrances.
- [x] Buttons use tactile `scale(.96)` press behavior.
- [x] Use generated image bob/float animations, disabled under `prefers-reduced-motion`.
- [x] Add a favicon so browser console is clean.
- [ ] Verify mobile and desktop have no horizontal overflow.

## Verification

- [ ] Focused PHPUnit suite passes.
- [ ] Full PHPUnit suite passes.
- [ ] Playwright checks landing, login, invite builder, Hotel mode, share screen, recipient invite, confirmation, dashboard, and reveal/profile gate.
- [ ] Browser console has no meaningful errors.

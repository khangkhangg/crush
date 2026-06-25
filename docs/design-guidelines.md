# Crush — UI Design Guidelines

A small, reusable component vocabulary for the sender-side screens (everything rendered through `templates/layout.php`). Keep these consistent; prefer a component class over a new inline style. Crush rule: **icons only, never emojis**.

## Tokens

| Token | Value | Use |
|---|---|---|
| Accent (pink) | `#ff3d8b` | primary actions, selected state |
| Accent soft | `#ff8fc0` | focus rings, envelope accents |
| Ink | `#5a2a52` | body text |
| Lilac field border | `#e7d4ff` | input borders |
| Lilac surface | `#f4ecff` / `#faf2ff` | segmented track, cards |
| Radius | 10–14px controls, 16–24px cards, 999px pills | |
| Gap | 6–12px within a group, 12–16px between groups | |

## Components

### `.seg` — segmented control (single-select, 2–3 options)
A connected pill bar where the selected segment is a raised white pill. Use for short, mutually-exclusive choices (delivery method, timing, mode). Built from radios so it works without JS and is keyboard-accessible.

```html
<span class="label">How will you send it?</span>
<div class="seg" role="radiogroup" aria-label="How will you send it?">
  <label><input type="radio" name="delivery" value="email" checked><span>Email it</span></label>
  <label><input type="radio" name="delivery" value="link"><span>Share the link</span></label>
</div>
```

### `.chips` / `.chip` — choice chips (single-select, many options)
A wrap of rounded pills; the selected one fills with the accent. Use when there are more options than fit a segmented control (cuisines). Pair with an "Other" chip that reveals a `.field` text input for free entry.

```html
<div class="chips">
  <label class="chip"><input type="radio" name="opts[0][cuisine]" value="Italian"><span>Italian</span></label>
  <label class="chip"><input type="radio" name="opts[0][cuisine]" value="__other__" data-other><span>Other</span></label>
</div>
<input class="field iv-other" name="opts[0][cuisine_custom]" placeholder="cuisine" hidden>
```

### `.field` / `.label`
The standard input look and the small section label above a control group.

## Accessibility
- Selection state is conveyed by a real `:checked` radio, not just color — keyboard and screen-reader friendly.
- `:focus-visible` gives every control a visible focus ring.
- Group controls in a `role="radiogroup"` with an `aria-label`.

## Principles
- One accent. Selected = filled/raised; unselected = quiet outline.
- Progressive enhancement: the markup is valid + submittable with JS off; JS only adds reveal/animation niceties.
- Reuse `.seg`/`.chips`/`.field` instead of bespoke inline styles so the look stays coherent and future screens inherit it.

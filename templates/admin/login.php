<?php $error = $error ?? null; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Admin sign in') ?></title>
<link rel="icon" href="/favicon.ico">
<style>
  *{box-sizing:border-box}:root{color-scheme:dark;--bg:#05070d;--panel:#0c111d;--ink:#f8fbff;--muted:#8b98ad;--line:rgba(255,255,255,.1);--blue:#4f8cff;--pink:#ff4fa3;--ease:cubic-bezier(.2,0,0,1)}
  body{margin:0;min-height:100svh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 18% 12%,rgba(79,140,255,.25),transparent 28rem),radial-gradient(circle at 82% 12%,rgba(255,79,163,.18),transparent 24rem),linear-gradient(180deg,#070a12,#05070d);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
  [data-admin-login]{width:min(100%,980px);display:grid;grid-template-columns:1fr 420px;gap:22px;align-items:stretch}
  .hero,.box{border-radius:24px;background:linear-gradient(180deg,rgba(17,24,39,.92),rgba(12,17,29,.96));box-shadow:0 24px 80px rgba(0,0,0,.38),inset 0 0 0 1px var(--line)}
  .hero{padding:34px;display:flex;flex-direction:column;justify-content:space-between;min-height:440px}
  .mark{width:54px;height:54px;border-radius:18px;background:linear-gradient(135deg,var(--blue),var(--pink));box-shadow:0 18px 36px rgba(79,140,255,.25)}
  .kicker{color:#8bd3ff;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.hero h1{font-size:clamp(38px,6vw,72px);line-height:.92;margin:14px 0;text-wrap:balance}.hero p{color:var(--muted);line-height:1.55;max-width:580px;text-wrap:pretty}
  .box{padding:28px}.box h2{margin:0 0 6px;font-size:24px}.hint{margin:0 0 18px;color:var(--muted)}label{display:grid;gap:8px;margin-top:13px;color:#d8e2f5;font-size:13px;font-weight:800}
  input{width:100%;min-height:48px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:rgba(3,6,14,.72);color:var(--ink);font:inherit;outline:none}input:focus-visible{border-color:var(--blue);box-shadow:0 0 0 4px rgba(79,140,255,.18)}
  button{margin-top:18px;width:100%;min-height:50px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--blue),#6f5cff);color:#fff;font:inherit;font-weight:900;cursor:pointer;transition:scale .12s var(--ease),filter .16s var(--ease)}button:active{scale:.96}button:hover{filter:brightness(1.08)}
  .err{color:#ffc2d1;background:rgba(255,79,119,.12);border:1px solid rgba(255,79,119,.28);border-radius:14px;padding:11px 12px;font-size:14px;margin:0 0 14px}
  @media(max-width:820px){[data-admin-login]{grid-template-columns:1fr}.hero{min-height:280px}.box{order:-1}}@media(max-width:520px){body{padding:12px}.hero,.box{border-radius:20px;padding:22px}.hero h1{font-size:40px}}
</style></head>
<body>
  <main data-admin-login>
    <section class="hero" aria-label="Admin console">
      <div><div class="mark" aria-hidden="true"></div><p class="kicker">Crush Admin</p><h1>Configure the app with clarity.</h1><p>Didudi-style operations console for product settings, invite delivery, themes, languages, and safety controls.</p></div>
      <p>Modern, darker, focused, and built for real configuration work.</p>
    </section>
    <form class="box" method="post" action="/admin/login">
      <p class="kicker">Admin access</p>
      <h2>Sign in</h2>
      <p class="hint">Use an admin account to manage Crush.</p>
      <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <label>Email <input type="email" name="email" required autocomplete="username"></label>
      <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
      <button type="submit">Sign in</button>
    </form>
  </main>
</body></html>

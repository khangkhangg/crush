<?php $error = $error ?? null; $sent = $sent ?? null; $name = $name ?? ''; $email = $email ?? ''; ?>
<!doctype html>
<html lang="<?= $e($lang ?? 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <style>
    :root{ --pink:#ff3d8b; --ink:#5a2a52; }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;font-family:ui-rounded,"Segoe UI",system-ui,sans-serif;color:var(--ink);
      background:linear-gradient(160deg,#ffd9ec 0%,#e7d4ff 55%,#d4f0ff 100%);
      min-height:100svh;display:flex;align-items:center;justify-content:center;overflow:hidden;
      -webkit-font-smoothing:antialiased;}
    .float{position:fixed;color:#fff6;animation:drift 9s ease-in-out infinite;}
    .float svg{width:100%;height:100%}
    @keyframes drift{0%,100%{transform:translateY(0) rotate(-6deg)}50%{transform:translateY(-22px) rotate(8deg)}}
    .stage{width:min(94vw,420px);text-align:center;padding:24px;position:relative;z-index:1}
    .mascot{width:84px;height:84px;margin:0 auto 6px;color:var(--pink);
      animation:bob 3s ease-in-out infinite;filter:drop-shadow(0 8px 14px rgba(255,61,139,.3))}
    @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    .word{font-size:46px;font-weight:900;letter-spacing:-1px;margin:2px 0;line-height:1;
      display:inline-flex;align-items:center;gap:8px;}
    .word .hb{width:38px;height:38px;color:var(--pink);animation:beat 1.2s ease-in-out infinite}
    @keyframes beat{0%,100%{transform:scale(1)}15%{transform:scale(1.25)}30%{transform:scale(1)}}
    .tag{opacity:.8;margin:6px 0 20px;text-wrap:balance;font-size:15px}
    .card{background:#fff;border-radius:24px;padding:18px;
      box-shadow:0 1px 2px rgba(90,42,82,.08),0 16px 34px rgba(157,123,255,.28)}
    .card--wide{width:min(94vw,640px)}
    .row{display:flex;flex-direction:column;gap:10px}
    .row input{padding:13px;border-radius:14px;border:1px solid #f0d9ea;font-size:16px;font-family:inherit}
    .go{padding:14px;border:0;border-radius:16px;background:var(--pink);color:#fff;font-weight:800;font-size:16px;
      cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;min-height:48px;
      transition:scale .12s cubic-bezier(.2,0,0,1),box-shadow .2s;box-shadow:0 6px 0 #c81e68}
    .go:active{scale:.97;box-shadow:0 3px 0 #c81e68}
    .go svg{width:18px;height:18px}
    .err{color:#b3243b;font-size:13px;margin:0 0 8px}
    .fine{font-size:12px;opacity:.6;margin-top:12px}
  </style>
</head>
<body>
<?php include __DIR__ . '/../partials/lang_switcher.php'; ?>
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="l-heart" viewBox="0 0 24 24"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" fill="currentColor"/></symbol>
    <symbol id="l-spark" viewBox="0 0 24 24"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z" fill="currentColor"/></symbol>
    <symbol id="l-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
  </svg>

  <span class="float" style="top:12%;left:10%;width:30px;height:30px;animation-delay:-1s"><svg><use href="#l-heart"/></svg></span>
  <span class="float" style="top:22%;right:12%;width:22px;height:22px;animation-delay:-3s"><svg><use href="#l-spark"/></svg></span>
  <span class="float" style="bottom:16%;left:16%;width:26px;height:26px;animation-delay:-5s"><svg><use href="#l-spark"/></svg></span>
  <span class="float" style="bottom:20%;right:14%;width:34px;height:34px;animation-delay:-2s"><svg><use href="#l-heart"/></svg></span>

  <main class="stage">
    <div class="mascot"><svg width="84" height="84"><use href="#l-mail"/></svg></div>
    <div class="word">Crush <span class="hb"><svg width="38" height="38"><use href="#l-heart"/></svg></span></div>
    <p class="tag">Send your crush a date — anonymously, adorably.</p>

    <div class="card">
      <?php if ($sent): ?>
        <p style="margin:6px 0;">Check your email — we sent a sign-in link to <strong><?= $e($sent) ?></strong>. Open it to keep going.</p>
      <?php else: ?>
        <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
        <form method="post" action="/" class="row">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
          <input name="name" value="<?= $e($name) ?>" placeholder="your name" required autocomplete="name">
          <input type="email" name="email" value="<?= $e($email) ?>" placeholder="you@email.com" required autocomplete="email">
          <input type="password" name="password" placeholder="pick a password" required minlength="6" autocomplete="new-password"
                 style="padding:13px;border-radius:14px;border:1px solid #f0d9ea;font-size:16px;font-family:inherit;">
          <button type="submit" class="go">Start <svg><use href="#l-mail"/></svg></button>
        </form>
        <p class="fine">Pick a password — you'll use it to sign back in.</p>
      <?php endif; ?>
    </div>
  </main>
<?php include __DIR__ . '/../partials/analytics.php'; ?>
</body>
</html>

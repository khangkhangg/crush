<?php $error = $error ?? null; $sent = $sent ?? null; $name = $name ?? ''; $email = $email ?? ''; ?>
<!doctype html>
<html lang="<?= $e($lang ?? 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <link rel="icon" href="/favicon.ico">
  <style>
    :root{ --pink:#ff3d8b; --pink-2:#ff6fad; --ink:#4f254a; --muted:#7f5b78; --lilac:#e7d4ff; --ease:cubic-bezier(.2,0,0,1); }
    *,*::before,*::after{box-sizing:border-box}
    html,body{min-height:100%}
    body{margin:0;font-family:ui-rounded,"Segoe UI",system-ui,sans-serif;color:var(--ink);
      background:radial-gradient(circle at 16% 16%,rgba(255,255,255,.65),transparent 18rem),
        radial-gradient(circle at 82% 12%,rgba(255,232,163,.38),transparent 16rem),
        linear-gradient(160deg,#ffd9ec 0%,#e7d4ff 55%,#d4f0ff 100%);
      min-height:100svh;display:grid;place-items:center;padding:54px 18px 22px;overflow-x:hidden;
      -webkit-font-smoothing:antialiased;}
    .float{position:fixed;color:#fff7;animation:drift 9s ease-in-out infinite;pointer-events:none}
    .float svg{width:100%;height:100%}
    @keyframes drift{0%,100%{transform:translateY(0) rotate(-6deg)}50%{transform:translateY(-22px) rotate(8deg)}}
    .shell{width:min(100%,980px);display:grid;gap:18px;align-items:center;position:relative;z-index:1}
    .hero{text-align:center;display:grid;justify-items:center}
    .mascot{width:108px;height:108px;margin:0 auto 8px;color:var(--pink);
      animation:bob 3s ease-in-out infinite;filter:drop-shadow(0 10px 18px rgba(255,61,139,.28))}
    .mascot img{width:100%;height:100%;object-fit:contain}
    @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    .word{font-size:clamp(46px,11vw,72px);font-weight:950;letter-spacing:0;margin:2px 0;line-height:.95;
      display:inline-flex;align-items:center;gap:10px;text-wrap:balance}
    .word .hb{width:40px;height:40px;color:var(--pink);animation:beat 1.2s ease-in-out infinite}
    @keyframes beat{0%,100%{transform:scale(1)}15%{transform:scale(1.25)}30%{transform:scale(1)}}
    .tag{opacity:.9;margin:10px auto 0;text-wrap:balance;text-wrap:pretty;font-size:17px;line-height:1.45;max-width:29rem}
    .card{background:rgba(255,255,255,.94);border-radius:30px;padding:20px;
      box-shadow:0 1px 2px rgba(90,42,82,.08),0 24px 56px rgba(157,123,255,.25);backdrop-filter:blur(14px);outline:1px solid rgba(255,255,255,.72)}
    .card--wide{width:min(94vw,640px)}
    .start-card{align-self:center}
    .row{display:flex;flex-direction:column;gap:11px}
    .field-wrap{display:grid;gap:6px}
    .field-label{font-size:12px;font-weight:900;color:#7a5472}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .row input{min-height:52px;padding:13px 15px;border-radius:17px;border:1px solid #f0d9ea;font-size:16px;font-family:inherit;outline:none;transition:border-color .15s var(--ease),box-shadow .15s var(--ease),transform .15s var(--ease)}
    .row input:focus-visible{border-color:#ff8fc0;box-shadow:0 0 0 4px rgba(255,143,192,.18)}
    .row input:focus{transform:translateY(-1px)}
    .go{min-height:54px;padding:14px;border:0;border-radius:18px;background:var(--pink);color:#fff;font-weight:900;font-size:17px;
      cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
      transition:scale .12s var(--ease),box-shadow .2s var(--ease),transform .2s var(--ease);box-shadow:0 7px 0 #c81e68}
    .go:hover{transform:translateY(-1px)}
    .go:active{scale:.96;box-shadow:0 3px 0 #c81e68}
    .go svg{width:18px;height:18px}
    .err{color:#b3243b;font-size:14px;margin:0 0 10px;background:#fff0f4;border-radius:14px;padding:10px 12px}
    .fine{font-size:13px;color:var(--muted);line-height:1.45;margin:13px 2px 0;text-wrap:pretty}
    .fine a{color:var(--pink);font-weight:800;text-decoration:none}
    .sent{line-height:1.55;text-wrap:pretty;margin:4px 0}
    @media (min-width:780px){
      body{padding:42px}
      .shell{grid-template-columns:minmax(300px,.92fr) minmax(380px,1.08fr);gap:42px;align-items:center}
      .hero{text-align:left;justify-items:start}
      .mascot{margin-left:0;width:118px;height:118px}
      .card{padding:24px}
      .tag{margin-left:0}
    }
    @media (min-width:1040px) and (max-height:820px){
      .mascot{width:104px;height:104px}.word{font-size:64px}.tag{font-size:16px}
    }
    @media (min-width:1080px){.word .hb{width:50px;height:50px}}
    @media (prefers-reduced-motion: reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
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

  <main class="shell" data-redesign-page="landing">
    <section class="hero" aria-labelledby="landing-title">
      <div class="mascot"><img src="/assets/generated/crush-mascot.png" alt="" loading="eager" decoding="async"></div>
      <h1 id="landing-title" class="word">Crush <span class="hb"><svg width="42" height="42"><use href="#l-heart"/></svg></span></h1>
      <p class="tag"><?= $e($t('Ask your crush out — stay anonymous, keep it cute.')) ?></p>
    </section>
    <section class="card start-card" aria-label="Start">
      <?php if ($sent): ?>
        <p class="sent"><?= $e($t('Check your email — your magic link is going to')) ?> <strong><?= $e($sent) ?></strong>. <?= $e($t('Open it and we’ll keep going.')) ?></p>
      <?php else: ?>
        <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
        <form method="post" action="/" class="row">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
          <label class="field-wrap"><span class="field-label sr-only"><?= $e($t('your name')) ?></span><input name="name" value="<?= $e($name) ?>" placeholder="<?= $e($t('your name')) ?>" required autocomplete="name"></label>
          <label class="field-wrap"><span class="field-label sr-only"><?= $e($t('your email')) ?></span><input type="email" name="email" value="<?= $e($email) ?>" placeholder="<?= $e($t('your email')) ?>" required autocomplete="email"></label>
          <label class="field-wrap"><span class="field-label sr-only"><?= $e($t('make a password')) ?></span><input type="password" name="password" placeholder="<?= $e($t('make a password')) ?>" required minlength="6" autocomplete="new-password"></label>
          <button type="submit" class="go"><?= $e($t('Start my invite')) ?> <svg><use href="#l-mail"/></svg></button>
        </form>
        <p class="fine"><?= $e($t('Make a password so you can hop back in later.')) ?> <a href="/about"><?= $e($t('Wait, what is Crush?')) ?></a></p>
      <?php endif; ?>
    </section>
  </main>
<?php include __DIR__ . '/../partials/analytics.php'; ?>
</body>
</html>

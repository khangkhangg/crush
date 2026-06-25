<?php
$sent  = $sent  ?? null;
$error = $error ?? null;
$title = $title ?? 'Sign in';
$cardClass = 'card--wide';
$inner = function () use ($e, $t, $csrf, $title, $sent, $error) {
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <div class="quest-grid auth-grid" data-redesign-page="login">
    <section class="quest-main">
      <div class="auth-mark auth-mark--art" aria-hidden="true"><img src="/assets/generated/crush-mascot.png" alt="" loading="eager" decoding="async"></div>
      <p class="eyebrow">Crush</p>
      <h1 class="title"><?= !empty($sent) ? $e($t('Check your email')) : $e($t('Sign in')) ?></h1>
      <?php if (!empty($sent)): ?>
        <p class="copy"><?= $e($t('We sent a magic sign-in link to')) ?> <strong><?= $e($sent) ?></strong>. <?= $e($t('Open it on this device to continue.')) ?></p>
      <?php else: ?>
        <p class="copy"><?= $e($t('Sign in to send someone a date invite.')) ?></p>
        <?php if (!empty($error)): ?><p role="alert" class="auth-error"><?= $e($error) ?></p><?php endif; ?>
        <form method="post" action="/login" class="auth-form">
          <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
          <input class="field" type="email" name="email" placeholder="<?= $e($t('you@email.com')) ?>" required autocomplete="username">
          <input class="field" type="password" name="password" placeholder="<?= $e($t('password')) ?>" required autocomplete="current-password">
          <button type="submit" class="btn"><?= $e($t('Sign in')) ?></button>
        </form>
        <p class="auth-small"><?= $e($t('No account yet?')) ?> <a href="/"><?= $e($t('Start here')) ?></a></p>
        <div class="auth-alt" aria-label="<?= $e($t('Other ways to sign in')) ?>">
          <form method="post" action="/login/magic">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input class="field" type="email" name="email" placeholder="<?= $e($t('you@email.com')) ?>" required autocomplete="username">
            <button type="submit" class="btn btn--soft"><?= $e($t('Email me a magic link')) ?></button>
          </form>
          <a href="/auth/google" class="btn btn--ghost"><?= $e($t('Continue with Google')) ?></a>
        </div>
      <?php endif; ?>
    </section>
    <aside class="quest-side"><div class="mini-scene auth-scene"><img class="generated-art generated-art--float generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"><div class="auth-ticket"><svg><use href="#ic-heart"/></svg><span></span><span></span><span></span></div></div></aside>
  </div>
  <style>
    .auth-grid{align-items:center}
    .auth-mark{width:70px;height:70px;border-radius:24px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;background:linear-gradient(150deg,#fff,#fff0f7);color:#ff3d8b;box-shadow:0 14px 28px rgba(255,61,139,.18);animation:auth-bob 3s ease-in-out infinite}
    .auth-mark svg{width:36px;height:36px}.auth-mark--art{width:86px;height:86px;background:transparent;box-shadow:none}.auth-mark--art img{width:100%;height:100%;object-fit:contain}@keyframes auth-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
    .auth-form,.auth-alt form{display:flex;flex-direction:column;gap:12px}.auth-form{margin-top:18px}
    .auth-error{color:#b3243b;background:#fff0f4;border-radius:14px;padding:10px 12px;margin:12px 0 0}
    .auth-small{color:#87607f;text-align:center;margin:16px 0 0}.auth-small a{color:#ff3d8b;font-weight:800;text-decoration:none}
    .auth-alt{margin-top:14px;display:grid;gap:10px}.auth-alt .btn{width:100%;box-shadow:none}
    .auth-ticket{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-3deg);width:min(78%,260px);min-height:170px;border-radius:28px;background:#fff;box-shadow:0 18px 34px rgba(90,42,82,.14);padding:24px;display:grid;gap:12px;justify-items:center}
    .auth-ticket svg{width:44px;height:44px;color:#ff3d8b}.auth-ticket span{display:block;width:100%;height:12px;border-radius:999px;background:#f4ecff}.auth-ticket span:nth-child(3){width:82%}.auth-ticket span:nth-child(4){width:56%;background:#ffe3f1}
    @media (max-width:759px){.auth-mark{margin-inline:auto}.quest-main{text-align:left}}@media (prefers-reduced-motion:reduce){.auth-mark{animation:none}}
  </style>
  <?php return ob_get_clean();
};
$body = $inner();
include __DIR__ . '/../layout.php';

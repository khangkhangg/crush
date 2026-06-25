<?php $reveal = $reveal ?? null; $wasAnonymous = $wasAnonymous ?? false; ?>
<!doctype html>
<html lang="<?= $e($lang ?? 'en') ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css">
<style>
  .envelope { width:200px; height:140px; margin:8px auto 22px; position:relative;
    animation:env-rise .7s cubic-bezier(.2,.8,.2,1) both; }
  @keyframes env-rise { from { transform:translateY(14px); opacity:0; } to { transform:translateY(0); opacity:1; } }
  .envelope .body { position:absolute; inset:0; background:#fff; border:2px solid #ff8fc0; border-radius:12px;
    box-shadow:0 10px 24px rgba(255,61,139,.22); }
  .envelope .letter { position:absolute; left:14px; right:14px; top:-14px; height:70px; background:#fff;
    border:2px solid #ffd1e6; border-radius:8px; animation:letter-out 1s ease .3s both; }
  @keyframes letter-out { from { top:30px; } to { top:-14px; } }
  .envelope .flap { position:absolute; left:0; right:0; top:0; height:0; border-left:100px solid transparent;
    border-right:100px solid transparent; border-top:72px solid #ff5fa6; border-radius:12px 12px 0 0;
    transform-origin:top; animation:flap-open .6s ease both; }
  @keyframes flap-open { from { transform:rotateX(0); } to { transform:rotateX(160deg); } }
  .envelope .heart { position:absolute; left:50%; top:54%; transform:translate(-50%,-50%); width:34px; height:34px; color:#ff3d8b; }
</style>
</head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/lang_switcher.php'; ?>
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card confirm-card">
  <div class="envelope" aria-hidden="true">
    <div class="body"></div>
    <div class="letter"></div>
    <div class="flap"></div>
    <svg class="heart"><use href="#ic-heart"/></svg>
  </div>
  <h1><?= $e($t('Your answer is on its way')) ?></h1>
  <p class="when"><?= $e($t('You picked')) ?> <strong><?= $e($when) ?></strong>.</p>
  <?php if ($reveal && $wasAnonymous): ?>
    <p class="reveal"><?= $e($t('Your secret admirer is')) ?> <strong><?= $e($reveal) ?></strong>.</p>
  <?php elseif ($reveal && !$wasAnonymous): ?>
    <p class="reveal"><?= $e($t('It\'s a date with')) ?> <strong><?= $e($reveal) ?></strong>.</p>
  <?php else: ?>
    <p class="reveal"><?= $e($t('They\'ll be in touch soon.')) ?></p>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/analytics.php'; ?>
</body></html>

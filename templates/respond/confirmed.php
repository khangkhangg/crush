<?php $reveal = $reveal ?? null; $wasAnonymous = $wasAnonymous ?? false; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card confirm-card">
  <svg class="big-ic"><use href="#ic-heart"/></svg>
  <h1>Your answer is on its way</h1>
  <p class="when">You picked <strong><?= $e($when) ?></strong>.</p>
  <?php if ($reveal && $wasAnonymous): ?>
    <p class="reveal">Your secret admirer is <strong><?= $e($reveal) ?></strong>.</p>
  <?php elseif ($reveal && !$wasAnonymous): ?>
    <p class="reveal">It's a date with <strong><?= $e($reveal) ?></strong>.</p>
  <?php else: ?>
    <p class="reveal">They'll be in touch soon.</p>
  <?php endif; ?>
</main>
</body></html>

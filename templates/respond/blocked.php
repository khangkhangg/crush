<?php $known = $known ?? true; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/bubblegum.css"></head>
<body class="theme-bubblegum"><main class="card">
  <?php if ($known): ?>
    <h1 style="text-wrap:balance;">You won't hear from them again</h1>
    <p class="subtitle">We've stopped this sender from inviting you. Take care.</p>
  <?php else: ?>
    <p class="subtitle">This link is no longer valid.</p>
  <?php endif; ?>
</main></body></html>

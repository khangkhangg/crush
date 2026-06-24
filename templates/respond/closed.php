<?php $reason = $reason ?? 'This invite is no longer available.'; $theme = $theme ?? 'bubblegum'; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>"><main class="card"><p class="subtitle"><?= $e($reason) ?></p></main></body></html>

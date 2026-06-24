<?php $error = $error ?? null; $sent = $sent ?? null; $name = $name ?? ''; $email = $email ?? ''; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title><?= $e($title ?? 'Crush') ?></title></head>
<body>
<?php if ($sent): ?>
  <p>Check your email — we sent a sign-in link to <?= $e($sent) ?>.</p>
<?php else: ?>
  <?php if ($error): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input name="name" value="<?= $e($name) ?>" placeholder="your name">
    <input name="email" value="<?= $e($email) ?>" placeholder="you@email.com">
    <button type="submit">Start</button>
  </form>
<?php endif; ?>
</body></html>

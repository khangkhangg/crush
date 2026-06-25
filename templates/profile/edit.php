<?php $error = $error ?? null; $user = $user ?? []; $avatars = $avatars ?? []; $returnTo = $returnTo ?? ''; ?>
<?php $content = function () use ($e, $error, $user, $avatars, $csrf, $returnTo) {
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/avatars.php'; ?>
  <h1 style="text-wrap:balance;">Make it yours</h1>
  <p style="opacity:.8;margin-top:0;">A few cute details so your crush knows it's really you.</p>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <?php include __DIR__ . '/_form.php'; ?>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

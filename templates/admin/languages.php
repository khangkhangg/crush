<?php /* templates/admin/languages.php */ $languages = $languages ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $languages, $flash) { ob_start(); ?>
  <div class="panel" data-admin-page="languages"><p class="admin-kicker">Copy system</p><h1>Languages</h1>
  <p>Manage translated UI copy for Crush. Keep tone clear, local, and consistent.</p>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <ul class="admin-list">
    <?php foreach ($languages as $code => $name): ?>
      <li><a href="/admin/languages/edit?lang=<?= $e($code) ?>"><?= $e($name) ?><small><?= $e($code) ?></small></a></li>
    <?php endforeach; ?>
  </ul></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

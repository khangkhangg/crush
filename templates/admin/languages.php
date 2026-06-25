<?php /* templates/admin/languages.php */ $languages = $languages ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $languages, $flash) { ob_start(); ?>
  <div class="panel"><h1>Languages</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <ul style="list-style:none;padding:0;">
    <?php foreach ($languages as $code => $name): ?>
      <li style="padding:6px 0;"><a href="/admin/languages/edit?lang=<?= $e($code) ?>"><?= $e($name) ?> (<?= $e($code) ?>)</a></li>
    <?php endforeach; ?>
  </ul></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

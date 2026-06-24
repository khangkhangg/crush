<?php $templates = $templates ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $templates, $flash) { ob_start(); ?>
  <div class="panel"><h1>Email templates</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <table><tr><th>Key</th><th>Lang</th><th></th></tr>
  <?php foreach ($templates as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['lang']) ?></td>
      <td><a href="/admin/templates/edit?key=<?= $e($t['key']) ?>&lang=<?= $e($t['lang']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

<?php $targets = $targets ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $targets, $flash) { ob_start(); ?>
  <div class="panel"><h1>Share buttons</h1>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <table><tr><th>Key</th><th>Label</th><th>Enabled</th><th></th></tr>
  <?php foreach ($targets as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['label']) ?></td>
      <td><?= ((int) $t['enabled'] === 1) ? 'yes' : 'no' ?></td>
      <td><a href="/admin/share/edit?key=<?= $e($t['key']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

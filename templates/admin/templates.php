<?php $templates = $templates ?? []; $flash = $flash ?? null; ?>
<?php $content = function () use ($e, $templates, $flash) { ob_start(); ?>
  <div class="panel" data-admin-page="templates"><p class="admin-kicker">Email</p><h1>Email templates</h1>
  <p>Edit transactional email subjects and bodies across supported languages.</p>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <div class="table-wrap">
  <table><tr><th>Key</th><th>Lang</th><th></th></tr>
  <?php foreach ($templates as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['lang']) ?></td>
      <td><a href="/admin/templates/edit?key=<?= $e($t['key']) ?>&amp;lang=<?= $e($t['lang']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table></div></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

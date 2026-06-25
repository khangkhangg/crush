<?php $themes = $themes ?? []; ?>
<?php $content = function () use ($e, $themes, $csrf) {
  ob_start(); ?>
  <div class="panel" data-admin-page="themes">
    <p class="admin-kicker">Experiments</p>
    <h1>Themes &amp; A/B funnel</h1>
    <p>Review recipient invite theme performance and control which experiences are active.</p>
    <form method="post" action="/admin/themes">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <div class="table-wrap">
      <table>
        <tr><th>Theme</th><th>Opened</th><th>Completed</th><th>Rate</th><th>Weight</th><th>Active</th></tr>
        <?php foreach ($themes as $t): ?>
          <tr>
            <td><?= $e($t['name']) ?></td>
            <td><?= $e((string) $t['opened']) ?></td>
            <td><?= $e((string) $t['completed']) ?></td>
            <td><?= $e((string) $t['rate']) ?>%</td>
            <td><input type="number" name="weight[<?= $e($t['key']) ?>]" value="<?= $e((string) $t['weight']) ?>" min="0" style="width:70px"></td>
            <td><input type="checkbox" name="active[<?= $e($t['key']) ?>]" <?= $t['is_active'] ? 'checked' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <div class="admin-actions"><button type="submit">Save themes</button></div>
    </form>
  </div>
  <?php return (string) ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

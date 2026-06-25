<?php $targets = $targets ?? []; $flash = $flash ?? null; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $targets, $flash, $csrf) { ob_start(); ?>
  <div class="panel" data-admin-page="share"><p class="admin-kicker">Channels</p><h1>Share buttons</h1>
  <p>Control the channels shown when a sender shares a private invite link.</p>
  <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
  <div class="table-wrap">
  <table><tr><th>Key</th><th>Label</th><th>Enabled</th><th></th></tr>
  <?php foreach ($targets as $t): ?>
    <tr><td><?= $e($t['key']) ?></td><td><?= $e($t['label']) ?></td>
      <td><?= ((int) $t['enabled'] === 1) ? 'yes' : 'no' ?></td>
      <td><a href="/admin/share/edit?key=<?= $e($t['key']) ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </table>
  </div>
  <h2 style="margin-top:18px;">Add a button</h2>
  <form method="post" action="/admin/share/new" class="admin-section">
    <input type="hidden" name="csrf" value="<?= $e($csrf ?? '') ?>">
    <input type="text" name="key" placeholder="key (e.g. reddit)">
    <input type="text" name="label" placeholder="Label">
    <input type="text" name="icon" placeholder="icon id (e.g. ic-share)" value="ic-share">
    <input type="text" name="url_template" placeholder="https://… with {url}">
    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="enabled" value="1" checked> Enabled</label>
    <div class="admin-actions"><button type="submit">Add button</button></div>
  </form>
  </div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

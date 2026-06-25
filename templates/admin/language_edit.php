<?php /* templates/admin/language_edit.php */ $lang = $lang ?? 'en'; $rows = $rows ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $lang, $rows, $csrf) { ob_start(); ?>
  <div class="panel" data-admin-page="language-edit"><p class="admin-kicker">Translation editor</p><h1>Edit <?= $e($lang) ?></h1>
  <form method="post" action="/admin/languages">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="lang" value="<?= $e($lang) ?>">
    <div class="table-wrap"><table><tr><th>English</th><th>Translation</th></tr>
    <?php foreach ($rows as $key => $value): ?>
      <tr><td><input type="text" name="keys[]" value="<?= $e($key) ?>" readonly style="width:100%"></td>
          <td><input type="text" name="values[]" value="<?= $e($value) ?>" style="width:100%"></td></tr>
    <?php endforeach; ?>
      <tr><td><input type="text" name="keys[]" placeholder="English phrase"></td>
          <td><input type="text" name="values[]" placeholder="translation"></td></tr>
    </table></div>
    <div class="admin-actions"><button type="submit">Save</button></div>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

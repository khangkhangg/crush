<?php /* templates/admin/language_edit.php */ $lang = $lang ?? 'en'; $rows = $rows ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $lang, $rows, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($lang) ?></h1>
  <form method="post" action="/admin/languages">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="lang" value="<?= $e($lang) ?>">
    <table style="width:100%"><tr><th>English</th><th>Translation</th></tr>
    <?php foreach ($rows as $key => $value): ?>
      <tr><td><input type="text" name="keys[]" value="<?= $e($key) ?>" readonly style="width:100%"></td>
          <td><input type="text" name="values[]" value="<?= $e($value) ?>" style="width:100%"></td></tr>
    <?php endforeach; ?>
      <tr><td><input type="text" name="keys[]" placeholder="English phrase" style="width:100%"></td>
          <td><input type="text" name="values[]" placeholder="translation" style="width:100%"></td></tr>
    </table>
    <button type="submit">Save</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

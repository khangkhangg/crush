<?php $tpl = $tpl ?? []; $placeholders = $placeholders ?? ''; ?>
<?php $content = function () use ($e, $tpl, $placeholders, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($tpl['key']) ?> / <?= $e($tpl['lang']) ?></h1>
  <p style="font-size:12px;opacity:.7">Placeholders: <?= $e($placeholders) ?></p>
  <form method="post" action="/admin/templates">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="key" value="<?= $e($tpl['key']) ?>">
    <input type="hidden" name="lang" value="<?= $e($tpl['lang']) ?>">
    <label>Subject <input type="text" name="subject" value="<?= $e($tpl['subject']) ?>"></label>
    <label>Body (HTML) <textarea name="body_html" rows="12" style="width:100%;font-family:monospace"><?= $e($tpl['body_html']) ?></textarea></label>
    <button type="submit">Save template</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

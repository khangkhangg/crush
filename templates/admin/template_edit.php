<?php $tpl = $tpl ?? []; $placeholders = $placeholders ?? ''; ?>
<?php $content = function () use ($e, $tpl, $placeholders, $csrf) { ob_start(); ?>
  <div class="panel" data-admin-page="template-edit"><p class="admin-kicker">Email editor</p><h1>Edit <?= $e($tpl['key']) ?> / <?= $e($tpl['lang']) ?></h1>
  <p>Placeholders: <?= $e($placeholders) ?></p>
  <form method="post" action="/admin/templates">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="key" value="<?= $e($tpl['key']) ?>">
    <input type="hidden" name="lang" value="<?= $e($tpl['lang']) ?>">
    <label>Subject <input type="text" name="subject" value="<?= $e($tpl['subject']) ?>"></label>
    <label>Body (HTML) <textarea name="body_html" rows="12" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"><?= $e($tpl['body_html']) ?></textarea></label>
    <div class="admin-actions"><button type="submit">Save template</button></div>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

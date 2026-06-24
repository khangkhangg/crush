<?php $target = $target ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $target, $csrf) { ob_start(); ?>
  <div class="panel"><h1>Edit <?= $e($target['key']) ?></h1>
  <p style="font-size:12px;opacity:.7">Use {url} where the invite link goes. Allowed: http(s), sms, mailto.</p>
  <form method="post" action="/admin/share">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="key" value="<?= $e($target['key']) ?>">
    <label>Label <input type="text" name="label" value="<?= $e($target['label']) ?>"></label>
    <label>URL template <input type="text" name="url_template" value="<?= $e($target['url_template']) ?>" style="width:100%"></label>
    <label><input type="checkbox" name="enabled" value="1" <?= ((int) $target['enabled'] === 1) ? 'checked' : '' ?>> Enabled</label>
    <button type="submit">Save</button>
  </form></div>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

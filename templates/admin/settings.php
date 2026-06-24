<?php
$flash  = $flash ?? null;
$values = $values ?? [];
$keys   = $keys ?? [];
$csrf   = $csrf ?? '';
$content = function () use ($e, $flash, $values, $keys, $csrf): string {
    ob_start(); ?>
  <div class="panel">
    <h1>Settings</h1>
    <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
    <form method="post" action="/admin/settings">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <?php foreach ($keys as $key): ?>
        <label><?= $e($key) ?>
          <input type="text" name="<?= $e($key) ?>" value="<?= $e($values[$key] ?? '') ?>">
        </label>
      <?php endforeach; ?>
      <button type="submit">Save settings</button>
    </form>
    <form method="post" action="/admin/settings/test" style="margin-top:8px">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <button type="submit">Send test email</button>
    </form>
  </div>
  <?php return (string) ob_get_clean();
};
$body = $content();
include __DIR__ . '/layout.php';

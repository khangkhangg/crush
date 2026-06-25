<?php $error = $error ?? null; ?>
<?php $content = function () use ($e, $t, $error, $csrf) {
  ob_start(); ?>
  <h1 style="text-wrap:balance;"><?= $e($t('Reset password')) ?></h1>
  <p style="opacity:.8;margin-top:0;"><?= $e($t('Choose a new password for your Crush account.')) ?></p>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/profile/password" style="display:flex;flex-direction:column;gap:14px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label style="font-size:13px;font-weight:600;opacity:.7;"><?= $e($t('New password')) ?>
      <input class="field" type="password" name="password" required minlength="6" autocomplete="new-password">
    </label>
    <label style="font-size:13px;font-weight:600;opacity:.7;"><?= $e($t('Confirm password')) ?>
      <input class="field" type="password" name="password_confirm" required minlength="6" autocomplete="new-password">
    </label>
    <button type="submit" style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;"><?= $e($t('Reset password')) ?></button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

<?php
$flash  = $flash ?? null;
$values = $values ?? [];
$keys   = $keys ?? [];
$csrf   = $csrf ?? '';
$content = function () use ($e, $flash, $values, $keys, $csrf): string {
    $groups = [
        'Mail delivery' => [
            'description' => 'Choose the mail driver and sender identity used for invites, magic links, and result emails.',
            'keys' => ['mail_driver', 'from_email', 'from_name', 'resend_api_key', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption'],
        ],
        'Sign-in providers' => [
            'description' => 'Configure Google OAuth credentials and redirect behavior.',
            'keys' => ['google_client_id', 'google_client_secret', 'google_redirect_uri'],
        ],
        'Operations' => [
            'description' => 'Set product-level rules that affect invite lifecycle and cleanup.',
            'keys' => ['invite_expiry_days'],
        ],
    ];
    $labels = [
        'mail_driver' => 'Mail driver',
        'from_email' => 'From email',
        'from_name' => 'From name',
        'resend_api_key' => 'Resend API key',
        'smtp_host' => 'SMTP host',
        'smtp_port' => 'SMTP port',
        'smtp_user' => 'SMTP user',
        'smtp_pass' => 'SMTP password',
        'smtp_encryption' => 'SMTP encryption',
        'google_client_id' => 'Google client ID',
        'google_client_secret' => 'Google client secret',
        'google_redirect_uri' => 'Google redirect URI',
        'invite_expiry_days' => 'Invite expiry days',
    ];
    $allowed = array_flip($keys);
    ob_start(); ?>
  <div class="panel" data-admin-page="settings">
    <p class="admin-kicker">App configuration</p>
    <h1>Settings</h1>
    <p>Keep the core Crush app configuration grouped by operational area so updates are easier to scan and safer to make.</p>
    <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
    <form method="post" action="/admin/settings">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <div class="admin-grid">
      <?php foreach ($groups as $heading => $group): ?>
        <section class="admin-section">
          <div class="admin-section-head">
            <div><h2><?= $e($heading) ?></h2><p><?= $e($group['description']) ?></p></div>
          </div>
          <div class="admin-form-grid">
            <?php foreach ($group['keys'] as $key): if (!isset($allowed[$key])) { continue; } ?>
              <label class="<?= in_array($key, ['resend_api_key', 'google_client_id', 'google_client_secret', 'google_redirect_uri'], true) ? 'span-2' : '' ?>"><?= $e($labels[$key] ?? $key) ?>
                <input type="<?= str_contains($key, 'secret') || str_contains($key, 'pass') || str_contains($key, 'api_key') ? 'password' : 'text' ?>" name="<?= $e($key) ?>" value="<?= $e($values[$key] ?? '') ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
      </div>
      <div class="admin-actions"><button type="submit">Save settings</button></div>
    </form>
    <form method="post" action="/admin/settings/test" class="admin-actions">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <button type="submit" class="admin-btn--ghost">Send test email</button>
    </form>
  </div>
  <?php return (string) ob_get_clean();
};
$body = $content();
include __DIR__ . '/layout.php';

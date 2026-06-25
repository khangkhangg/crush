<?php
$flash  = $flash ?? null;
$values = $values ?? [];
$keys   = $keys ?? [];
$csrf   = $csrf ?? '';
$content = function () use ($e, $flash, $values, $keys, $csrf): string {
    $allowed = array_flip($keys);

    // Helper: render a single text/password input label
    $field = static function (string $key, string $label, array $values, callable $e, string $extraClass = '') use ($allowed): string {
        if (!isset($allowed[$key])) {
            return '';
        }
        $isPassword = str_contains($key, 'secret') || str_contains($key, 'pass')
                   || str_contains($key, 'api_key') || str_contains($key, 'token');
        $type = $isPassword ? 'password' : 'text';
        $cls  = $extraClass !== '' ? ' class="' . $extraClass . '"' : '';
        return '<label' . $cls . '>' . $e($label)
            . '<input type="' . $type . '" name="' . $e($key) . '" value="' . $e($values[$key] ?? '') . '">'
            . '</label>';
    };

    // Helper: build a <select> with options, marking the current value as selected
    $selectField = static function (string $key, string $label, array $options, array $values, callable $e) use ($allowed): string {
        if (!isset($allowed[$key])) {
            return '';
        }
        $current = $values[$key] ?? '';
        $optHtml = '';
        foreach ($options as $val => $text) {
            $sel      = ($val === $current) ? ' selected' : '';
            $optHtml .= '<option value="' . $e((string) $val) . '"' . $sel . '>' . $e($text) . '</option>';
        }
        return '<label>' . $e($label) . '<select name="' . $e($key) . '">' . $optHtml . '</select></label>';
    };

    // Helper: render a per-provider test form (separate from main save form)
    $testForm = static function (string $provider, string $label, string $csrf, callable $e): string {
        return '<form method="post" action="/admin/settings/test">'
            . '<input type="hidden" name="csrf" value="' . $e($csrf) . '">'
            . '<input type="hidden" name="provider" value="' . $e($provider) . '">'
            . '<button type="submit" class="admin-btn--ghost">Test ' . $e($label) . '</button>'
            . '</form>';
    };

    ob_start(); ?>
  <div class="panel" data-admin-page="settings">
    <p class="admin-kicker">App configuration</p>
    <h1>Settings</h1>
    <p>Keep the core Crush app configuration grouped by operational area so updates are easier to scan and safer to make.</p>
    <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>

    <?php /* ============================================================
           MAIN SAVE FORM — wraps all credential/settings inputs.
           Per-provider test forms are placed BELOW (never nested).
           ============================================================ */ ?>
    <form method="post" action="/admin/settings">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <div class="admin-grid">

        <?php /* ---- MAIL DELIVERY ---- */ ?>
        <section class="admin-section">
          <div class="admin-section-head">
            <div>
              <h2>Mail delivery</h2>
              <p>Choose the primary and backup mail provider, sender identity, and per-provider credentials used for invites, magic links, and result emails.</p>
            </div>
          </div>

          <?php /* Routing sub-block */ ?>
          <div class="admin-form-grid">
            <?= $selectField('mail_driver', 'Primary driver', [
                'php'     => 'PHP mail()',
                'resend'  => 'Resend',
                'mailjet' => 'Mailjet',
                'smtp'    => 'SMTP',
            ], $values, $e) ?>
            <?= $selectField('mail_backup', 'Backup driver', [
                'none'    => 'None (no failover)',
                'php'     => 'PHP mail()',
                'resend'  => 'Resend',
                'mailjet' => 'Mailjet',
                'smtp'    => 'SMTP',
            ], $values, $e) ?>
            <?= $field('from_email', 'From email', $values, $e) ?>
            <?= $field('from_name',  'From name',  $values, $e) ?>
          </div>

          <?php /* Resend card */ ?>
          <div class="admin-form-grid">
            <h3 class="admin-provider-heading">Resend</h3>
            <?= $field('resend_api_key', 'Resend API key', $values, $e, 'span-2') ?>
          </div>

          <?php /* Mailjet card */ ?>
          <div class="admin-form-grid">
            <h3 class="admin-provider-heading">Mailjet</h3>
            <?= $field('mailjet_api_key',    'Mailjet API key',    $values, $e) ?>
            <?= $field('mailjet_secret_key', 'Mailjet secret key', $values, $e) ?>
          </div>

          <?php /* SMTP card */ ?>
          <div class="admin-form-grid">
            <h3 class="admin-provider-heading">SMTP</h3>
            <?= $field('smtp_host',       'SMTP host',       $values, $e) ?>
            <?= $field('smtp_port',       'SMTP port',       $values, $e) ?>
            <?= $field('smtp_user',       'SMTP user',       $values, $e) ?>
            <?= $field('smtp_pass',       'SMTP password',   $values, $e) ?>
            <?= $field('smtp_encryption', 'SMTP encryption', $values, $e) ?>
          </div>

          <?php /* Telegram card */ ?>
          <div class="admin-form-grid">
            <h3 class="admin-provider-heading">Telegram</h3>
            <?= $field('telegram_bot_token', 'Bot token', $values, $e) ?>
            <?= $field('telegram_chat_id',   'Chat ID',   $values, $e) ?>
          </div>
        </section>

        <?php /* ---- SIGN-IN PROVIDERS ---- */ ?>
        <?php
        $signInKeys  = ['google_client_id', 'google_client_secret', 'google_redirect_uri'];
        $signInLabels = [
            'google_client_id'     => 'Google client ID',
            'google_client_secret' => 'Google client secret',
            'google_redirect_uri'  => 'Google redirect URI',
        ];
        ?>
        <section class="admin-section">
          <div class="admin-section-head">
            <div><h2>Sign-in providers</h2><p>Configure Google OAuth credentials and redirect behavior.</p></div>
          </div>
          <div class="admin-form-grid">
            <?php foreach ($signInKeys as $key): if (!isset($allowed[$key])) { continue; } ?>
              <label class="span-2"><?= $e($signInLabels[$key] ?? $key) ?>
                <input type="<?= str_contains($key, 'secret') || str_contains($key, 'pass') || str_contains($key, 'api_key') || str_contains($key, 'token') ? 'password' : 'text' ?>" name="<?= $e($key) ?>" value="<?= $e($values[$key] ?? '') ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </section>

        <?php /* ---- OPERATIONS ---- */ ?>
        <?php
        $opsKeys  = ['invite_expiry_days'];
        $opsLabels = ['invite_expiry_days' => 'Invite expiry days'];
        ?>
        <section class="admin-section">
          <div class="admin-section-head">
            <div><h2>Operations</h2><p>Set product-level rules that affect invite lifecycle and cleanup.</p></div>
          </div>
          <div class="admin-form-grid">
            <?php foreach ($opsKeys as $key): if (!isset($allowed[$key])) { continue; } ?>
              <label><?= $e($opsLabels[$key] ?? $key) ?>
                <input type="text" name="<?= $e($key) ?>" value="<?= $e($values[$key] ?? '') ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </section>

      </div>
      <div class="admin-actions"><button type="submit">Save settings</button></div>
    </form>

    <?php /* ============================================================
           PER-PROVIDER TEST FORMS — outside the save form (no nesting).
           ============================================================ */ ?>
    <div class="admin-actions admin-test-actions">
      <?= $testForm('resend',   'Resend',   $csrf, $e) ?>
      <?= $testForm('mailjet',  'Mailjet',  $csrf, $e) ?>
      <?= $testForm('smtp',     'SMTP',     $csrf, $e) ?>
      <?= $testForm('telegram', 'Telegram', $csrf, $e) ?>
    </div>

  </div>
  <?php return (string) ob_get_clean();
};
$body = $content();
include __DIR__ . '/layout.php';

<?php
$forbidden = $forbidden ?? false;
$driver    = $driver ?? '';
$blocks    = $blocks ?? 0;
$content = function () use ($e, $forbidden, $driver, $blocks): string {
    if ($forbidden) {
        return '<div class="panel" data-admin-page="forbidden"><h1>Forbidden</h1><p>You need admin access.</p></div>';
    }
    ob_start(); ?>
  <div data-admin-page="dashboard" class="admin-grid">
    <section class="panel">
      <p class="admin-kicker">Configuration hub</p>
      <h1>Admin dashboard</h1>
      <p>Control the operational parts of Crush: email delivery, invite themes, language copy, moderation, and share channels.</p>
      <div class="admin-actions">
        <a class="admin-btn" href="/admin/settings">Configure app</a>
        <a class="admin-btn admin-btn--ghost" href="/admin/themes">Review themes</a>
      </div>
    </section>
    <section class="admin-stat-grid" aria-label="Admin summary">
      <div class="admin-stat"><span>Mail driver</span><strong><?= $e($driver) ?></strong></div>
      <div class="admin-stat"><span>Recent blocks</span><strong><?= $e((string) $blocks) ?></strong></div>
      <div class="admin-stat"><span>Config areas</span><strong>6</strong></div>
    </section>
    <section class="panel">
      <h2>Configuration map</h2>
      <ul class="admin-list">
        <li><a href="/admin/settings">Settings<small>Mail, Google, expiry</small></a></li>
        <li><a href="/admin/templates">Email templates<small>Invite and result messages</small></a></li>
        <li><a href="/admin/languages">Languages<small>Translation copy</small></a></li>
        <li><a href="/admin/moderation">Moderation<small>Search and block</small></a></li>
      </ul>
    </section>
  </div>
  <?php return (string) ob_get_clean();
};
$body = $content();
include __DIR__ . '/layout.php';

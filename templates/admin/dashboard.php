<?php
$forbidden = $forbidden ?? false;
$driver    = $driver ?? '';
$blocks    = $blocks ?? 0;
$content = function () use ($e, $forbidden, $driver, $blocks): string {
    if ($forbidden) {
        return '<div class="panel"><h1>Forbidden</h1><p>You need admin access.</p></div>';
    }
    ob_start(); ?>
  <div class="panel">
    <h1>Admin dashboard</h1>
    <p>Active mail driver: <strong><?= $e($driver) ?></strong></p>
    <p>Recent blocks: <strong><?= $e($blocks) ?></strong></p>
  </div>
  <?php return (string) ob_get_clean();
};
$body = $content();
include __DIR__ . '/layout.php';

<?php $invites = $invites ?? []; $blocks = $blocks ?? []; $search = $search ?? ''; ?>
<?php $content = function () use ($e, $invites, $blocks, $search, $csrf) {
  ob_start(); ?>
  <div class="panel" data-admin-page="moderation">
    <p class="admin-kicker">Safety</p>
    <h1>Moderation</h1>
    <p>Find recent invites, investigate abuse reports, and block sender-to-recipient combinations.</p>
    <form method="get" action="/admin/moderation">
      <label>Search by crush email <input type="text" name="q" value="<?= $e((string) $search) ?>"></label>
      <div class="admin-actions"><button type="submit">Search</button></div>
    </form>
    <div class="table-wrap">
    <table>
      <tr><th>Crush email</th><th>Status</th><th>Sender</th><th></th></tr>
      <?php foreach ($invites as $inv): ?>
        <tr>
          <td><?= $e($inv['crush_email']) ?></td>
          <td><?= $e($inv['status']) ?></td>
          <td><?= $e((string) $inv['sender_id']) ?></td>
          <td>
            <form method="post" action="/admin/block" style="margin:0">
              <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
              <input type="hidden" name="sender_id" value="<?= $e((string) $inv['sender_id']) ?>">
              <input type="hidden" name="crush_email" value="<?= $e($inv['crush_email']) ?>">
              <button type="submit">Block</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <h2>Recent blocks</h2>
    <div class="table-wrap">
    <table>
      <tr><th>Sender</th><th>Crush email</th><th>Reason</th></tr>
      <?php foreach ($blocks as $b): ?>
        <tr><td><?= $e((string) $b['sender_id']) ?></td><td><?= $e($b['crush_email']) ?></td><td><?= $e((string) ($b['reason'] ?? '')) ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>
  <?php return (string) ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

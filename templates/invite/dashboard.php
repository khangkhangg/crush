<?php $invites = $invites ?? []; $appUrl = $appUrl ?? ''; ?>
<?php $content = function () use ($e, $invites, $appUrl) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invites</h1>
  <a href="/invites/new"
     style="display:inline-block;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">
    Send a new crush invite
  </a>
  <?php if (empty($invites)): ?>
    <p style="opacity:.75;margin-top:20px;">No invites yet. Send your first one above.</p>
  <?php else: ?>
    <ul style="list-style:none;padding:0;margin-top:20px;display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($invites as $inv): ?>
        <li style="padding:14px;border-radius:14px;background:#faf2ff;border:1px solid #eadcff;">
          <strong><?= $e($inv['crush_name'] ?: $inv['crush_email']) ?></strong>
          <span style="float:right;font-size:12px;opacity:.7;"><?= $e($inv['status']) ?></span>
          <div style="font-size:12px;opacity:.7;margin-top:4px;word-break:break-all;">
            <?= $e($appUrl) ?>/i/<?= $e($inv['public_token']) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

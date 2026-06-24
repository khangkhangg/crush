<?php $invites = $invites ?? []; $appUrl = $appUrl ?? ''; ?>
<?php
$badge = static function (string $status): array {
  return [
    'sent'           => ['Waiting', '#9d7bff'],
    'responded'      => ['Answered', '#ff3d8b'],
    'pending_sender' => ['Needs you', '#f59e0b'],
    'confirmed'      => ['Confirmed', '#16a34a'],
    'closed'         => ['Closed', '#9aa0a6'],
  ][$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#9aa0a6'];
};
$content = function () use ($e, $invites, $appUrl, $badge) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invites</h1>
  <a href="/invites/new"
     style="display:inline-block;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">
    Send a new crush invite
  </a>
  <?php if (empty($invites)): ?>
    <p style="opacity:.75;margin-top:20px;">No invites yet. Send your first one above.</p>
  <?php else: ?>
    <ul style="list-style:none;padding:0;margin-top:20px;display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($invites as $inv):
        [$label, $color] = $badge((string) $inv['status']);
        $answered = in_array($inv['status'], ['responded', 'pending_sender', 'confirmed', 'closed'], true); ?>
        <li style="padding:14px 16px;border-radius:16px;background:#faf2ff;border:1px solid #eadcff;display:flex;align-items:center;gap:12px;">
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;"><?= $e($inv['crush_name'] ?: $inv['crush_email'] ?: 'A secret crush') ?></div>
            <span class="iv-badge" style="display:inline-block;margin-top:4px;font-size:11px;font-weight:700;color:#fff;background:<?= $e($color) ?>;padding:2px 9px;border-radius:999px;"><?= $e($label) ?></span>
          </div>
          <?php if ($answered): ?>
            <a href="/invites/<?= $e($inv['public_token']) ?>/response"
               style="padding:9px 14px;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">View</a>
          <?php else: ?>
            <button type="button" class="iv-copy" data-link="<?= $e(rtrim($appUrl, '/') . '/i/' . $inv['public_token']) ?>"
               style="padding:9px 14px;border-radius:12px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;white-space:nowrap;">Copy link</button>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <script>
    document.querySelectorAll('.iv-copy').forEach(function(b){
      b.addEventListener('click', function(){
        var t = b.getAttribute('data-link');
        if (navigator.clipboard && t) navigator.clipboard.writeText(t).then(function(){
          var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
        });
      });
    });
    </script>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

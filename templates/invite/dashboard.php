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
  <style>
    .iv-card { border-radius:16px; background:#faf2ff; border:1px solid #eadcff; overflow:hidden; }
    .iv-head { padding:14px 16px; display:flex; align-items:center; gap:12px; }
    .iv-detail { max-height:0; opacity:0; overflow:hidden; transition:max-height .35s ease, opacity .3s ease; }
    .iv-card.open .iv-detail { max-height:1600px; opacity:1; }
    .iv-detail .inner { padding:2px 16px 16px; }
    .iv-detail .inner h1 { font-size:20px; margin:.2em 0; }
    @media (prefers-reduced-motion: reduce) { .iv-detail { transition:none; } }
  </style>
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
        <li class="iv-card">
          <div class="iv-head">
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;"><?= $e($inv['crush_name'] ?: $inv['crush_email'] ?: 'A secret crush') ?></div>
              <span class="iv-badge" style="display:inline-block;margin-top:4px;font-size:11px;font-weight:700;color:#fff;background:<?= $e($color) ?>;padding:2px 9px;border-radius:999px;"><?= $e($label) ?></span>
            </div>
            <?php if ($answered): ?>
              <a class="iv-view" href="/invites/<?= $e($inv['public_token']) ?>/response"
                 style="padding:9px 14px;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">View</a>
            <?php else: ?>
              <button type="button" class="iv-copy" data-link="<?= $e(rtrim($appUrl, '/') . '/i/' . $inv['public_token']) ?>"
                 style="padding:9px 14px;border-radius:12px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;white-space:nowrap;">Copy link</button>
            <?php endif; ?>
          </div>
          <?php if ($answered): ?><div class="iv-detail"></div><?php endif; ?>
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
    // Progressive enhancement: expand the answer inline instead of navigating.
    document.querySelectorAll('.iv-view').forEach(function(a){
      a.addEventListener('click', function(e){
        var card = a.closest('.iv-card');
        var det = card && card.querySelector('.iv-detail');
        if (!det) return;                       // no-JS path already navigates via href
        e.preventDefault();
        if (card.classList.contains('open')) { card.classList.remove('open'); a.textContent = 'View'; return; }
        if (det.getAttribute('data-loaded')) { card.classList.add('open'); a.textContent = 'Hide'; return; }
        a.textContent = '…';
        fetch(a.href, { credentials: 'same-origin' })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var card2 = new DOMParser().parseFromString(html, 'text/html').querySelector('main.card');
            det.innerHTML = '<div class="inner">' + (card2 ? card2.innerHTML : 'Could not load the details.') + '</div>';
            // The injected detail's own <script> won't run; re-bind its copy-link button.
            det.querySelectorAll('.iv-reshare').forEach(function(b){
              b.addEventListener('click', function(){
                var tk = b.getAttribute('data-token');
                if (tk && navigator.clipboard) navigator.clipboard.writeText(location.origin + '/i/' + tk).then(function(){
                  var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
                });
              });
            });
            det.setAttribute('data-loaded', '1');
            card.classList.add('open'); a.textContent = 'Hide';
          })
          .catch(function(){ window.location.href = a.href; });   // fall back to navigation
      });
    });
    </script>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

<?php $invites = $invites ?? []; $appUrl = $appUrl ?? ''; $cardClass = 'card--dashboard'; ?>
<?php
$badge = static function (string $status): array {
  return [
    'sent'           => ['Waiting', '#9d7bff', 'Your invite is out there.'],
    'opened'         => ['Opened', '#9d7bff', 'They have seen it.'],
    'responded'      => ['Answered', '#ff3d8b', 'They picked details.'],
    'pending_sender' => ['Needs you', '#f59e0b', 'Review the answer.'],
    'confirmed'      => ['Confirmed', '#16a34a', 'It is a date.'],
    'closed'         => ['Closed', '#9aa0a6', 'This invite is closed.'],
  ][$status] ?? [ucfirst(str_replace('_', ' ', $status)), '#9aa0a6', 'Invite status updated.'];
};
$content = function () use ($e, $t, $invites, $appUrl, $badge) { ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <style>
    .dash-head { display:grid; gap:16px; margin-bottom:18px; }
    .dash-title { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .dash-title h1 { margin:0; font-size:clamp(32px,5vw,46px); line-height:1; text-wrap:balance; }
    .dash-title p { margin:8px 0 0; color:var(--muted); line-height:1.45; }
    .dash-layout { display:grid; gap:18px; }
    .dash-scene { display:none; }
    .iv-list { list-style:none; padding:0; margin:0; display:grid; gap:12px; }
    .iv-card { border-radius:24px; background:linear-gradient(180deg,#fff,#fff8fc); box-shadow:var(--shadow-soft); overflow:hidden; }
    .iv-head { padding:15px; display:grid; grid-template-columns:1fr auto; align-items:center; gap:12px; }
    .iv-person { min-width:0; display:flex; align-items:center; gap:11px; }
    .iv-avatar { flex:0 0 auto; width:44px; height:44px; border-radius:17px; display:grid; place-items:center; background:linear-gradient(150deg,#ffe3f1,#f4ecff); color:var(--pink); font-weight:950; box-shadow:inset 0 0 0 1px rgba(255,255,255,.7); }
    .iv-name { font-weight:950; font-size:17px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .iv-sub { margin-top:4px; color:var(--muted); font-size:12px; line-height:1.3; text-wrap:pretty; }
    .iv-badge { display:inline-flex; align-items:center; min-height:24px; margin-top:5px; font-size:11px; font-weight:900; color:#fff; padding:3px 9px; border-radius:999px; }
    .iv-view, .iv-copy { min-height:44px; border-radius:16px; padding:10px 14px; font-weight:900; text-decoration:none; white-space:nowrap; cursor:pointer; }
    .iv-view { background:var(--pink); color:#fff; box-shadow:0 5px 0 #c81e68; }
    .iv-copy { border:1px solid var(--lilac); background:#fff; color:var(--ink); }
    .iv-detail { max-height:0; opacity:0; overflow:hidden; transition:max-height .35s var(--ease), opacity .3s var(--ease); }
    .iv-card.open .iv-detail { max-height:1800px; opacity:1; }
    .iv-detail .inner { padding:0 15px 15px; }
    .iv-detail .inner main.card { width:100%; box-shadow:none; padding:0; background:transparent; border-radius:0; }
    .iv-detail .inner h1 { font-size:22px; margin:.2em 0; }
    .empty-state { padding:22px; border-radius:24px; background:linear-gradient(150deg,#fff,#fff8fc); box-shadow:var(--shadow-soft); text-align:center; }
    .empty-icon { width:80px; height:80px; margin:0 auto 12px; border-radius:28px; display:grid; place-items:center; color:var(--pink); background:linear-gradient(150deg,#ffe3f1,#f4ecff); }
    .empty-icon svg { width:42px; height:42px; }
    .empty-icon--art { width:132px; height:112px; background:transparent; }
    .empty-icon--art img { width:100%; height:100%; object-fit:contain; filter:drop-shadow(0 14px 22px rgba(255,61,139,.18)); }
    @media (min-width:860px) {
      .dash-layout { grid-template-columns:minmax(0,1fr) 320px; align-items:start; }
      .dash-scene { display:block; position:sticky; top:28px; }
      .dash-scene .soft-panel { margin-top:14px; }
      .dash-scene p { margin:6px 0 0; color:var(--muted); line-height:1.45; }
    }
    @media (max-width:520px) { .iv-head { grid-template-columns:1fr; } .iv-view, .iv-copy { justify-self:start; } }
    @media (prefers-reduced-motion: reduce) { .iv-detail { transition:none; } }
  </style>
  <header class="dash-head" data-redesign-page="dashboard">
    <div class="dash-title">
      <div>
        <p class="eyebrow"><?= $e($t('Your date quests')) ?></p>
        <h1><?= $e($t('Your invites')) ?></h1>
        <p><?= $e($t('Track every invite from sent to answered.')) ?></p>
      </div>
      <a href="/invites/new" class="btn"><?= $e($t('Send a new crush invite')) ?></a>
    </div>
  </header>
  <div class="dash-layout">
    <section>
      <?php if (empty($invites)): ?>
        <div class="empty-state">
          <div class="empty-icon empty-icon--art"><img src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"></div>
          <h2><?= $e($t('No invites yet')) ?></h2>
          <p class="copy"><?= $e($t('Send your first one when you are ready.')) ?></p>
        </div>
      <?php else: ?>
        <ul class="iv-list">
          <?php foreach ($invites as $inv):
            [$label, $color, $hint] = $badge((string) $inv['status']);
            $answered = in_array($inv['status'], ['responded', 'pending_sender', 'confirmed', 'closed'], true);
            $display = $inv['crush_name'] ?: $inv['crush_email'] ?: $t('A secret crush'); ?>
            <li class="iv-card">
              <div class="iv-head">
                <div class="iv-person">
                  <div class="iv-avatar" aria-hidden="true"><?= $e(substr((string) $display, 0, 1)) ?></div>
                  <div style="min-width:0;">
                    <div class="iv-name"><?= $e($display) ?></div>
                    <div class="iv-sub"><?= $e($t($hint)) ?></div>
                    <span class="iv-badge" style="background:<?= $e($color) ?>;"><?= $e($t($label)) ?></span>
                  </div>
                </div>
                <?php if ($answered): ?>
                  <a class="iv-view" href="/invites/<?= $e($inv['public_token']) ?>/response"><?= $e($t('View')) ?></a>
                <?php else: ?>
                  <button type="button" class="iv-copy" data-link="<?= $e(rtrim($appUrl, '/') . '/i/' . $inv['public_token']) ?>"><?= $e($t('Copy link')) ?></button>
                <?php endif; ?>
              </div>
              <?php if ($answered): ?><div class="iv-detail"></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <aside class="dash-scene" aria-hidden="true">
      <div class="mini-scene"><img class="generated-art generated-art--float generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"></div>
      <div class="soft-panel">
        <strong><?= $e($t('Invite timeline')) ?></strong>
        <p><?= $e($t('Sent, opened, answered, confirmed. The next step is always highlighted.')) ?></p>
      </div>
    </aside>
  </div>
  <script>
  document.querySelectorAll('.iv-copy').forEach(function(b){
    b.addEventListener('click', function(){
      var t = b.getAttribute('data-link');
      if (navigator.clipboard && t) navigator.clipboard.writeText(t).then(function(){
        var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
      });
    });
  });
  document.querySelectorAll('.iv-view').forEach(function(a){
    a.addEventListener('click', function(e){
      var card = a.closest('.iv-card');
      var det = card && card.querySelector('.iv-detail');
      if (!det) return;
      e.preventDefault();
      if (card.classList.contains('open')) { card.classList.remove('open'); a.textContent = 'View'; return; }
      if (det.getAttribute('data-loaded')) { card.classList.add('open'); a.textContent = 'Hide'; return; }
      a.textContent = '...';
      fetch(a.href, { credentials: 'same-origin' })
        .then(function(r){ return r.text(); })
        .then(function(html){
          var card2 = new DOMParser().parseFromString(html, 'text/html').querySelector('main.card');
          det.innerHTML = '<div class="inner">' + (card2 ? card2.innerHTML : 'Could not load the details.') + '</div>';
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
        .catch(function(){ window.location.href = a.href; });
    });
  });
  </script>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

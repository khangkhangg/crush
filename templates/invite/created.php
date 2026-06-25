<?php $link = $link ?? ''; $invite = $invite ?? []; $shareLinks = $shareLinks ?? []; ?>
<?php $content = function () use ($e, $t, $link, $invite, $shareLinks) {
  $who = ($invite['crush_name'] ?? '') ?: ($invite['crush_email'] ?? '');
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <div class="quest-grid ready-grid" data-redesign-page="share">
    <section class="quest-main">
      <div class="ready-mark ready-mark--art" aria-hidden="true"><img src="/assets/generated/invite-envelope.png" alt="" loading="eager" decoding="async"></div>
      <p class="eyebrow"><?= $e($t('Invite ready')) ?></p>
      <h1 class="title"><?= $e($t('Your invite is ready')) ?></h1>
      <p class="copy">
        <?php if ($who !== ''): ?>
          <?= $e($t('Share this private link with')) ?> <strong><?= $e($who) ?></strong>.
        <?php else: ?>
          <?= $e($t('Share your private invite link.')) ?>
        <?php endif; ?>
      </p>
      <div class="copy-row" style="display:flex;gap:8px;align-items:stretch;">
        <input id="lnk" readonly value="<?= $e($link) ?>" class="field link-field" onclick="this.select()">
        <button type="button" id="copyBtn" aria-label="<?= $e($t('Copy link')) ?>" class="btn copy-btn">
          <svg width="18" height="18"><use href="#ic-copy"/></svg>
        </button>
      </div>
      <span id="copiedMsg" aria-live="polite" class="copied-msg"><?= $e($t('Copied!')) ?></span>
      <div class="share-grid">
        <button type="button" id="nativeShare" hidden class="btn btn--soft share-action">
          <svg width="18" height="18"><use href="#ic-share"/></svg> <?= $e($t('Share')) ?>
        </button>
        <?php foreach ($shareLinks as $s): ?>
          <a href="<?= $e($s['href']) ?>" target="_blank" rel="noopener" class="btn btn--soft share-action">
            <svg width="18" height="18"><use href="#<?= $e($s['icon']) ?>"/></svg> <?= $e($s['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="back-link"><a href="/invites"><?= $e($t('Back to your invites')) ?></a></p>
    </section>
    <aside class="quest-side">
      <div class="mini-scene ready-scene"><img class="generated-art generated-art--float generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"></div>
      <div class="soft-panel ready-tip">
        <strong><?= $e($t('What happens next?')) ?></strong>
        <p><?= $e($t('They open the invite, pick a time and vibe, and you see the answer here.')) ?></p>
      </div>
    </aside>
  </div>
  <style>
    .ready-grid { align-items:center; }
    .ready-mark { width:74px;height:74px;border-radius:26px;display:grid;place-items:center;background:linear-gradient(150deg,#ff3d8b,#ff8fc0);color:#fff;box-shadow:0 14px 28px rgba(255,61,139,.22);margin-bottom:14px;animation:ready-pop .7s var(--ease) both; }
    .ready-mark svg { width:38px;height:38px; }
    .ready-mark--art{width:112px;height:112px;background:transparent;box-shadow:none}.ready-mark--art img{width:100%;height:100%;object-fit:contain}
    @keyframes ready-pop { from { opacity:0; transform:translateY(12px) scale(.88); } to { opacity:1; transform:translateY(0) scale(1); } }
    .copy-row { margin-top:18px; }
    .link-field { flex:1; min-width:0; font-size:13px; }
    .copy-btn { width:58px; padding-inline:0; flex:0 0 58px; }
    .copied-msg { display:block; min-height:20px; margin-top:9px; opacity:0; color:#16a34a; font-weight:900; font-size:13px; transition:opacity .2s var(--ease); }
    .share-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
    .share-action { min-height:44px; box-shadow:var(--shadow-soft); padding-inline:13px; }
    .back-link { margin:18px 0 0; }
    .back-link a { color:#ff3d8b; font-weight:900; text-decoration:none; }
    .ready-scene::before { animation:seal-wiggle 2.4s ease-in-out infinite; }
    @keyframes seal-wiggle { 0%,100%{ transform:translate(-50%,-50%) rotate(-2deg); } 50%{ transform:translate(-50%,-54%) rotate(2deg); } }
    .ready-tip { margin-top:16px; }
    .ready-tip p { margin:7px 0 0; color:var(--muted); line-height:1.5; text-wrap:pretty; }
    @media (max-width:759px) { .ready-mark { margin-inline:auto; } .quest-main { text-align:left; } }
    @media (prefers-reduced-motion: reduce) { .ready-mark, .ready-scene::before { animation:none; } }
  </style>
  <script>
  (function(){
    var url = <?= json_encode($link, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var copy = document.getElementById('copyBtn');
    var msg = document.getElementById('copiedMsg');
    function flashCopied(){
      if (msg) { msg.style.opacity = '1'; setTimeout(function(){ msg.style.opacity = '0'; }, 2000); }
      if (copy) { copy.style.scale = '0.96'; setTimeout(function(){ copy.style.scale = '1'; }, 150); }
    }
    if (copy) copy.addEventListener('click', function(){
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(flashCopied); }
      else { var l = document.getElementById('lnk'); if (l) { l.select(); document.execCommand('copy'); flashCopied(); } }
    });
    var ns = document.getElementById('nativeShare');
    if (ns && navigator.share) {
      ns.hidden = false;
      ns.style.display = 'inline-flex';
      ns.addEventListener('click', function(){ navigator.share({ url: url }).catch(function(){}); });
    }
  })();
  </script>
  <?php return ob_get_clean(); };
$cardClass = 'card--quest';
$body = $content();
include __DIR__ . '/../layout.php';

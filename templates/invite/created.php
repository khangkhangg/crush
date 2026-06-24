<?php $link = $link ?? ''; $invite = $invite ?? []; $shareLinks = $shareLinks ?? []; ?>
<?php $content = function () use ($e, $link, $invite, $shareLinks) {
  $who = $invite['crush_name'] ?: ($invite['crush_email'] ?: '');
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <h1 style="text-wrap:balance;">Your invite is ready</h1>
  <p style="opacity:.8;"><?= $who !== '' ? 'Share this private link with <strong>' . $e($who) . '</strong>:' : 'Share your private invite link:' ?></p>
  <div style="display:flex;gap:8px;">
    <input id="lnk" readonly value="<?= $e($link) ?>"
           style="flex:1;min-width:0;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;" onclick="this.select()">
    <button type="button" id="copyBtn" aria-label="Copy link"
            style="padding:0 14px;border:0;border-radius:12px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;">
      <svg width="18" height="18"><use href="#ic-copy"/></svg>
    </button>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;">
    <button type="button" id="nativeShare" hidden
            style="display:flex;align-items:center;gap:6px;padding:9px 12px;border:1px solid #e7d4ff;border-radius:12px;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">
      <svg width="18" height="18"><use href="#ic-share"/></svg> Share
    </button>
    <?php foreach ($shareLinks as $s): ?>
      <a href="<?= $e($s['href']) ?>" target="_blank" rel="noopener"
         style="display:flex;align-items:center;gap:6px;padding:9px 12px;border:1px solid #e7d4ff;border-radius:12px;color:#5a2a52;text-decoration:none;font-weight:600;">
        <svg width="18" height="18"><use href="#<?= $e($s['icon']) ?>"/></svg> <?= $e($s['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <p style="margin-top:18px;"><a href="/invites" style="color:#ff3d8b;font-weight:600;">Back to your invites</a></p>
  <script>
  (function(){
    var url = <?= json_encode($link, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var copy = document.getElementById('copyBtn');
    if (copy) copy.addEventListener('click', function(){
      navigator.clipboard && navigator.clipboard.writeText(url).then(function(){ copy.setAttribute('aria-label','Copied'); });
    });
    var ns = document.getElementById('nativeShare');
    if (ns && navigator.share) {
      ns.hidden = false;
      ns.addEventListener('click', function(){ navigator.share({ url: url }).catch(function(){}); });
    }
  })();
  </script>
  <?php return ob_get_clean(); };
$cardClass = 'card--wide';
$body = $content();
include __DIR__ . '/../layout.php';

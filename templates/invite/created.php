<?php $link = $link ?? ''; $invite = $invite ?? []; ?>
<?php $content = function () use ($e, $link, $invite) { ob_start(); ?>
  <h1 style="text-wrap:balance;">Your invite is ready</h1>
  <p style="opacity:.8;">Share this private link with <strong><?= $e($invite['crush_name'] ?: $invite['crush_email']) ?></strong>:</p>
  <input id="lnk" readonly value="<?= $e($link) ?>"
         style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;font-size:13px;"
         onclick="this.select()">
  <p style="margin-top:16px;"><a href="/invites" style="color:#ff3d8b;font-weight:600;">Back to your invites</a></p>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

<?php $message = $message ?? null; $unsubscribe = $unsubscribe ?? '#'; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;"><?= $e($senderLabel) ?> has a crush on you</h1>
  <?php if ($message): ?><p style="color:#444;line-height:1.5;"><?= $e($message) ?></p><?php endif; ?>
  <p style="color:#444;">Tap below to pick a date, a meal, and where to meet.</p>
  <p><a href="<?= $e($link) ?>" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;">Open my invite</a></p>
  <p style="color:#999;font-size:12px;">If the button doesn't work, paste this link: <?= $e($link) ?></p>
  <p style="color:#bbb;font-size:11px;margin-top:18px;">Not interested? <a href="<?= $e($unsubscribe) ?>" style="color:#bbb;">Block &amp; report</a>.</p>
</div>

<?php $name = $name ?? null; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;">Welcome to Crush<?= $name ? ', ' . $e($name) : '' ?></h1>
  <p style="color:#444;line-height:1.5;">Your account is ready. Sign in and add a few cute details to your profile.</p>
  <p><a href="<?= $e($link) ?>" style="display:inline-block;padding:12px 20px;background:#ff3d8b;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;">Sign in &amp; finish my profile</a></p>
  <p style="color:#999;font-size:12px;">Or paste this link: <?= $e($link) ?></p>
</div>

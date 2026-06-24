<?php
$sent  = $sent  ?? null;
$error = $error ?? null;
$title = $title ?? 'Sign in';
$inner = function () use ($e, $csrf, $title, $sent, $error) {
  ob_start(); ?>
  <h1 style="margin:0 0 6px;font-size:24px;text-wrap:balance;">Crush</h1>
  <?php if (!empty($sent)): ?>
    <p>We sent a magic sign-in link to <strong><?= $e($sent) ?></strong>. Open it on this device to continue.</p>
  <?php else: ?>
    <p style="opacity:.8;margin-top:0;">Sign in to send someone a date invite.</p>
    <?php if (!empty($error)): ?>
      <p role="alert" style="color:#b3243b;"><?= $e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/login" style="display:flex;flex-direction:column;gap:12px;">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <input class="i" type="email" name="email" placeholder="you@email.com" required
             style="padding:12px;border-radius:14px;border:1px solid #e7d4ff;font-size:16px;">
      <input class="i" type="password" name="password" placeholder="password" required autocomplete="current-password"
             style="padding:12px;border-radius:14px;border:1px solid #e7d4ff;font-size:16px;">
      <button type="submit"
              style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">
        Sign in
      </button>
    </form>
    <p style="text-align:center;margin:14px 0 6px;opacity:.6;">No account yet? <a href="/" style="color:#ff3d8b;font-weight:600;">Start here</a></p>
    <details style="margin-top:6px;">
      <summary style="cursor:pointer;opacity:.7;font-size:14px;">Other ways to sign in</summary>
      <form method="post" action="/login/magic" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input class="i" type="email" name="email" placeholder="you@email.com" required
               style="padding:10px;border-radius:12px;border:1px solid #e7d4ff;font-size:15px;">
        <button type="submit" style="padding:10px;border:0;border-radius:12px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">Email me a magic link</button>
      </form>
      <a href="/auth/google" style="display:block;text-align:center;margin-top:8px;padding:10px;border-radius:12px;border:1px solid #e7d4ff;color:#5a2a52;text-decoration:none;font-weight:600;">Continue with Google</a>
    </details>
  <?php endif;
  return ob_get_clean();
};
// Render the inner content, then wrap it in the shared layout.
$body = $inner();
include __DIR__ . '/../layout.php';

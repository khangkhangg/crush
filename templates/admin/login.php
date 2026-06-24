<?php $error = $error ?? null; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Admin sign in') ?></title>
<style>
  body{font-family:system-ui,sans-serif;background:#f6f6fb;color:#222;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
  .box{background:#fff;border-radius:14px;padding:28px;width:min(92vw,360px);box-shadow:0 1px 2px rgba(0,0,0,.05),0 10px 26px rgba(0,0,0,.08)}
  h1{font-size:20px;margin:0 0 16px} label{display:block;font-size:13px;font-weight:600;color:#555;margin-top:10px}
  input{width:100%;padding:11px;border:1px solid #e3e3ef;border-radius:10px;font-size:16px;box-sizing:border-box}
  button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:#7a3cff;color:#fff;font-weight:700;font-size:16px;cursor:pointer}
  .err{color:#b3243b;font-size:14px;margin:0 0 8px}
</style></head>
<body>
  <form class="box" method="post" action="/admin/login">
    <h1>Admin sign in</h1>
    <?php if ($error): ?><p class="err" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Sign in</button>
  </form>
</body></html>

<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Admin') ?></title>
<style>
  body{font-family:system-ui,sans-serif;margin:0;background:#f6f6fb;color:#222;-webkit-font-smoothing:antialiased}
  .wrap{max-width:760px;margin:0 auto;padding:24px}
  nav a{margin-right:14px;color:#7a3cff;text-decoration:none;font-weight:600}
  .panel{background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.05),0 8px 20px rgba(0,0,0,.06);margin-top:16px}
  label{display:block;font-size:13px;font-weight:600;color:#555;margin-top:10px}
  input,select{width:100%;padding:9px;border:1px solid #e3e3ef;border-radius:10px;font-size:14px}
  button{margin-top:14px;padding:10px 16px;border:0;border-radius:10px;background:#7a3cff;color:#fff;font-weight:700;cursor:pointer}
  table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:8px;border-bottom:1px solid #eee;font-size:14px}
  .flash{background:#eef9f0;border:1px solid #cce9d4;color:#246b39;padding:10px;border-radius:10px;margin-top:12px}
</style></head>
<body><div class="wrap">
  <nav><a href="/admin">Dashboard</a><a href="/admin/settings">Settings</a><a href="/admin/themes">Themes</a><a href="/admin/moderation">Moderation</a><a href="/admin/templates">Templates</a><a href="/admin/share">Share</a></nav>
  <?= $body ?>
</div></body></html>

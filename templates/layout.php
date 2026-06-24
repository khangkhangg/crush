<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <style>
    :root { color-scheme: light; }
    body { font-family: ui-rounded, "Segoe UI", system-ui, sans-serif; margin:0;
           background:linear-gradient(160deg,#ffd9ec,#e7d4ff 55%,#d4f0ff); color:#5a2a52;
           -webkit-font-smoothing:antialiased; min-height:100vh; display:flex;
           align-items:center; justify-content:center; }
    .card { background:#fff; border-radius:24px; padding:32px; width:min(92vw,380px);
            box-shadow:0 1px 2px rgba(90,42,82,.08),0 12px 28px rgba(157,123,255,.22); }
  </style>
</head>
<body>
  <main class="card"><?= $body ?></main>
</body>
</html>

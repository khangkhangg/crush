<?php $cardClass = $cardClass ?? ''; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <style>
    *,*::before,*::after { box-sizing:border-box; }
    :root { color-scheme: light; }
    body { font-family: ui-rounded, "Segoe UI", system-ui, sans-serif; margin:0;
           background:linear-gradient(160deg,#ffd9ec,#e7d4ff 55%,#d4f0ff); color:#5a2a52;
           -webkit-font-smoothing:antialiased; min-height:100vh; display:flex;
           align-items:center; justify-content:center; }
    .card { background:#fff; border-radius:24px; padding:32px; width:min(92vw,380px);
            box-shadow:0 1px 2px rgba(90,42,82,.08),0 12px 28px rgba(157,123,255,.22); }
    .card--wide { width:min(94vw,640px); }
    .field { width:100%; padding:11px 13px; border-radius:12px; border:1px solid #e7d4ff; font-size:15px; font-family:inherit; background:#fff; color:inherit; }
    .label { display:block; font-size:13px; font-weight:600; opacity:.75; margin-bottom:6px; }
    .seg { display:inline-flex; flex-wrap:wrap; gap:6px; padding:4px; background:#f4ecff; border-radius:14px; }
    .seg label { position:relative; cursor:pointer; margin:0; }
    .seg input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .seg span { display:block; padding:9px 16px; border-radius:11px; font-weight:600; font-size:14px; color:#7a5e86; white-space:nowrap; transition:background .15s, color .15s, box-shadow .15s; }
    .seg input:checked + span { background:#fff; color:#ff3d8b; box-shadow:0 1px 3px rgba(157,123,255,.25); }
    .seg input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:1px; }
    .chips { display:flex; flex-wrap:wrap; gap:7px; }
    .chip { cursor:pointer; margin:0; }
    .chip input { position:absolute; opacity:0; width:0; height:0; }
    .chip span { display:inline-block; padding:7px 13px; border-radius:999px; border:1.5px solid #e7d4ff; font-size:13px; font-weight:600; color:#5a2a52; background:#fff; transition:background .15s, border-color .15s, color .15s; }
    .chip input:checked + span { background:#ff3d8b; border-color:#ff3d8b; color:#fff; }
    .chip input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:1px; }
  </style>
</head>
<body>
  <main class="card <?= $e($cardClass) ?>"><?= $body ?></main>
<?php include __DIR__ . '/partials/analytics.php'; ?>
</body>
</html>

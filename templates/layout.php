<?php $cardClass = $cardClass ?? ''; ?>
<!doctype html>
<html lang="<?= $e($lang ?? 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Crush') ?></title>
  <link rel="icon" href="/favicon.ico">
  <style>
    *,*::before,*::after { box-sizing:border-box; }
    :root {
      color-scheme: light;
      --ink:#4f254a; --muted:#8a6684; --pink:#ff3d8b; --pink-2:#ff6fad;
      --lilac:#e7d4ff; --lav:#f5edff; --sky:#d4f0ff; --mint:#d8f8df;
      --paper:rgba(255,255,255,.94); --shadow:0 1px 2px rgba(90,42,82,.08),0 26px 70px rgba(157,123,255,.28);
      --soft-shadow:0 14px 34px rgba(90,42,82,.11); --ease:cubic-bezier(.2,0,0,1);
    }
    html { min-height:100%; }
    body {
      font-family: ui-rounded, "Segoe UI", system-ui, sans-serif; margin:0; color:var(--ink);
      background:
        radial-gradient(circle at 14% 12%, rgba(255,255,255,.72), transparent 18rem),
        radial-gradient(circle at 88% 16%, rgba(255,228,162,.38), transparent 16rem),
        radial-gradient(circle at 50% 92%, rgba(212,240,255,.64), transparent 21rem),
        linear-gradient(160deg,#ffd9ec,#e7d4ff 54%,#d4f0ff);
      -webkit-font-smoothing:antialiased; min-height:100svh; display:flex; align-items:center; justify-content:center;
      padding:64px 18px 22px; overflow-x:hidden;
    }
    body::before, body::after {
      content:""; position:fixed; pointer-events:none; z-index:0; border-radius:999px; opacity:.7;
    }
    body::before { width:10px;height:10px;left:11%;top:17%;box-shadow:82vw 7vh 0 #fff8, 52vw 74vh 0 #fff8, 7vw 68vh 0 #fff8, 68vw 42vh 0 #fff8; }
    body::after { width:56px;height:56px;right:5%;bottom:8%;background:linear-gradient(135deg,#fff8,#ffe0f0);filter:blur(.2px);transform:rotate(14deg); }
    [hidden] { display:none !important; }
    a { color:var(--pink); }
    .card {
      position:relative; z-index:1; background:var(--paper); border-radius:30px; padding:24px;
      width:min(94vw,420px); box-shadow:var(--shadow); backdrop-filter:blur(14px);
      outline:1px solid rgba(255,255,255,.72);
    }
    .card--wide { width:min(94vw,720px); }
    .card--quest { width:min(95vw,1080px); }
    .card--dashboard { width:min(95vw,1040px); }
    .quest-grid { display:grid; gap:20px; }
    .quest-main { min-width:0; }
    .quest-side { display:none; }
    .title { font-size:clamp(30px,6vw,48px); line-height:1; margin:4px 0 10px; text-wrap:balance; letter-spacing:0; }
    .eyebrow { margin:0 0 6px; color:var(--pink); font-weight:900; letter-spacing:0; text-transform:none; }
    .copy { color:var(--muted); line-height:1.55; text-wrap:pretty; }
    .field, input.i, textarea {
      width:100%; min-height:48px; padding:12px 14px; border-radius:17px; border:1px solid #eadcff;
      font-size:16px; font-family:inherit; background:#fff; color:inherit; outline:none;
      transition:border-color .15s var(--ease), box-shadow .15s var(--ease), transform .15s var(--ease);
    }
    textarea { min-height:92px; resize:vertical; }
    .field:focus-visible, input.i:focus-visible, textarea:focus-visible {
      border-color:#ff8fc0; box-shadow:0 0 0 4px rgba(255,143,192,.18); transform:translateY(-1px);
    }
    .label { display:block; font-size:14px; font-weight:900; color:#805676; margin-bottom:8px; }
    .btn {
      min-height:52px; padding:13px 18px; border:0; border-radius:18px; background:var(--pink); color:#fff;
      font-weight:900; font-size:16px; font-family:inherit; cursor:pointer; text-decoration:none;
      display:inline-flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 7px 0 #c81e68;
      transition:transform .15s var(--ease), scale .12s var(--ease), box-shadow .15s var(--ease);
    }
    .btn:hover { transform:translateY(-1px); }
    .btn:active { scale:.96; box-shadow:0 3px 0 #c81e68; }
    .btn--soft { background:#fff; color:var(--pink); border:1px solid #eadcff; box-shadow:var(--soft-shadow); }
    .btn--ghost { background:#fff; color:var(--ink); border:1px solid #eadcff; box-shadow:none; }
    .seg { display:flex; flex-wrap:wrap; gap:8px; padding:6px; background:#f4ecff; border-radius:22px; }
    .seg label { position:relative; cursor:pointer; margin:0; flex:1 1 180px; min-width:0; }
    .seg input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .seg span {
      display:flex; min-height:48px; align-items:center; justify-content:center; padding:10px 14px; border-radius:17px;
      font-weight:900; font-size:15px; color:#7a5e86; text-align:center; transition:background .15s var(--ease), color .15s var(--ease), box-shadow .15s var(--ease), transform .15s var(--ease);
    }
    .seg input:checked + span { background:#fff; color:var(--pink); box-shadow:0 7px 20px rgba(157,123,255,.22); transform:translateY(-1px); }
    .seg input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:2px; }
    .chips { display:flex; flex-wrap:wrap; gap:10px; }
    .chip { cursor:pointer; margin:0; }
    .chip input { position:absolute; opacity:0; width:0; height:0; }
    .chip span {
      display:inline-flex; min-height:42px; align-items:center; padding:8px 14px; border-radius:999px; border:2px solid #e5cfff;
      font-size:14px; font-weight:900; color:var(--ink); background:#fff; transition:background .15s var(--ease), border-color .15s var(--ease), color .15s var(--ease), transform .15s var(--ease);
    }
    .chip input:checked + span { background:var(--pink); border-color:var(--pink); color:#fff; transform:translateY(-1px); }
    .chip input:focus-visible + span { outline:2px solid #ff8fc0; outline-offset:2px; }
    .soft-panel { border-radius:24px; padding:16px; background:linear-gradient(180deg,#fff,#fff8fc); box-shadow:var(--soft-shadow); outline:1px solid rgba(234,220,255,.8); }
    .mini-scene { min-height:100%; border-radius:30px; background:linear-gradient(150deg,#fff0f8,#f5efff 55%,#e6f7ff); position:relative; overflow:hidden; box-shadow:inset 0 0 0 1px rgba(255,255,255,.72); }
    .mini-scene::before,.mini-scene::after { content:""; position:absolute; border-radius:999px; background:#fff9; box-shadow:0 0 0 12px #fff3; }
    .mini-scene::before { width:42px;height:42px;left:28px;top:34px; }
    .mini-scene::after { width:24px;height:24px;right:56px;top:58px; }
    .generated-art { display:block; width:min(100%,320px); height:auto; object-fit:contain; filter:drop-shadow(0 18px 28px rgba(255,61,139,.2)); }
    .generated-art--float { animation:art-float 4.2s ease-in-out infinite; }
    .generated-art--small { width:min(100%,220px); }
    .generated-art--center { margin-inline:auto; }
    @keyframes art-float { 0%,100%{ transform:translateY(0) rotate(-1deg); } 50%{ transform:translateY(-10px) rotate(1deg); } }
    @media (min-width:760px) {
      body { padding:42px; }
      .card { padding:32px; }
      .quest-grid { grid-template-columns:minmax(0,1fr) minmax(280px,.72fr); align-items:stretch; }
      .quest-side { display:block; }
    }
    @media (max-width:520px) {
      body { align-items:flex-start; padding-inline:12px; }
      .card { width:100%; padding:20px; border-radius:26px; }
      .seg label { flex-basis:100%; }
      .title { font-size:32px; }
    }
    @media (prefers-reduced-motion: reduce) {
      *,*::before,*::after { animation:none !important; transition:none !important; scroll-behavior:auto !important; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/lang_switcher.php'; ?>
  <main class="card <?= $e($cardClass) ?>"><?= $body ?></main>
<?php include __DIR__ . '/partials/analytics.php'; ?>
</body>
</html>

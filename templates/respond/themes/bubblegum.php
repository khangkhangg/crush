<?php $message = $message ?? null; ?>
<!doctype html><html lang="<?= $e($lang ?? 'en') ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<link rel="icon" href="/favicon.ico">
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:64px 20px 24px;
    background:#ffe9f4 radial-gradient(circle at 20% 20%,#fff0fa,#ffd9ec 60%,#e7d4ff);
    font-family:"Trebuchet MS","Segoe UI",sans-serif;color:#7a2e6b;-webkit-font-smoothing:antialiased}
  .bg-page{width:min(94vw,440px);background:#fff;border-radius:10px;padding:26px 22px 24px;position:relative;transform:rotate(-1.2deg);
    box-shadow:0 2px 4px rgba(122,46,107,.12),0 18px 40px rgba(157,123,255,.3)}
  .bg-page:before{content:"";position:absolute;top:-12px;left:30px;width:90px;height:26px;background:rgba(255,209,102,.7);transform:rotate(-6deg);box-shadow:0 2px 4px rgba(0,0,0,.08)}
  .bg-page:after{content:"";position:absolute;top:-10px;right:26px;width:80px;height:24px;background:rgba(157,123,255,.55);transform:rotate(5deg)}
  .theme-art{display:block;width:min(58vw,180px);height:auto;margin:-72px auto 6px;filter:drop-shadow(0 18px 24px rgba(255,61,139,.24));animation:theme-float 4s ease-in-out infinite}
  .bg-title{font-size:26px;font-weight:900;color:#ff3d8b;text-shadow:1px 1px 0 #fff;text-wrap:balance;margin:6px 0;transform:rotate(.6deg)}
  .bg-msg{background:#fff7fb;border:1px dashed #ff9ccb;border-radius:8px;padding:10px;transform:rotate(-.8deg);line-height:1.5}
  .rf-error{color:#c81e68} .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:16px} .rf-field{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:700;color:#9a6a90}
  .rf-field input{padding:11px;border:2px solid #ffd0e8;border-radius:12px;font-family:inherit;font-size:16px}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-weight:800;color:#ff3d8b;margin-bottom:6px}
  .rf-chips{display:flex;flex-wrap:wrap;gap:10px} .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:8px 12px;background:#fff;border-radius:14px;cursor:pointer;box-shadow:0 2px 6px rgba(255,61,139,.25);transform:rotate(-2deg);transition:scale .12s,transform .12s}
  .rf-chip:nth-child(even){transform:rotate(2deg)} .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){box-shadow:0 0 0 3px #ff3d8b inset}
  .rf-ic{width:16px;height:16px;color:#ff3d8b} .rf-place{font-weight:800;color:#ff3d8b;transform:rotate(-1deg)}
  .rf-cta{min-height:50px;border:0;border-radius:999px;background:#ff3d8b;color:#fff;font-family:inherit;font-weight:800;font-size:17px;cursor:pointer;box-shadow:0 5px 0 #c81e68;transition:scale .12s} .rf-cta:active{scale:.96;box-shadow:0 2px 0 #c81e68}
  @keyframes theme-float{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-8px) rotate(2deg)}}
  @media (prefers-reduced-motion:reduce){.theme-art{animation:none}}
  @media (min-width:760px){body{padding:42px}.bg-page{width:min(94vw,520px)}.theme-art{width:210px}}
</style></head>
<body class="theme-bubblegum">
<?php include __DIR__ . '/../../partials/lang_switcher.php'; ?>
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<main class="bg-page">
  <img class="theme-art" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async">
  <h1 class="bg-title"><?= $e($senderLabel) ?> <?= $e($t('has a crush on u')) ?></h1>
  <?php if ($message): ?><p class="bg-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
<?php include __DIR__ . '/../../partials/analytics.php'; ?>
</body></html>

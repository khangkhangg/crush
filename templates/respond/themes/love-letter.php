<?php $message = $message ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:center;justify-content:center;padding:20px;
    background:repeating-linear-gradient(45deg,#efe2c8,#efe2c8 14px,#ece0c4 14px,#ece0c4 28px);
    font-family:Georgia,"Times New Roman",serif;color:#5a3b2e;-webkit-font-smoothing:antialiased}
  .ll-letter{width:min(94vw,440px);background:#fbf3e0;border-radius:6px;padding:30px 30px 26px;position:relative;
    box-shadow:0 2px 4px rgba(90,59,46,.15),0 24px 50px rgba(90,59,46,.28);
    background-image:linear-gradient(#fbf3e0 95%,#e8d9b8);background-size:100% 34px;}
  .ll-seal{width:64px;height:64px;border-radius:50%;margin:-54px auto 6px;display:flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 35% 30%,#d8556a,#a51f37);color:#fff;box-shadow:inset 0 -4px 8px rgba(0,0,0,.3);}
  .ll-seal svg{width:30px;height:30px} .ll-kicker{text-align:center;font-style:italic;font-size:22px;text-wrap:balance;margin:8px 0}
  .ll-msg{text-align:center;line-height:1.6;opacity:.9} .rf-error{color:#a51f37;text-align:center}
  .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:18px} .rf-field{display:flex;flex-direction:column;gap:4px;font-style:italic;font-size:14px}
  .rf-field input{padding:9px;border:0;border-bottom:1.5px solid #c8a96f;background:transparent;font-family:inherit;font-size:16px;color:#5a3b2e}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-style:italic;margin-bottom:8px}
  .rf-chips{display:flex;flex-wrap:wrap;gap:8px} .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:6px 12px;border:1.5px solid #c8a96f;border-radius:4px;cursor:pointer;transition:scale .12s}
  .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){background:#a51f37;color:#fff;border-color:#a51f37}
  .rf-ic{width:16px;height:16px} .rf-place{font-style:italic;color:#a51f37} .rf-cta{min-height:48px;border:0;border-radius:4px;background:#a51f37;color:#fbf3e0;font-family:inherit;font-size:17px;font-style:italic;cursor:pointer;box-shadow:0 4px 0 #6f1224;transition:scale .12s} .rf-cta:active{scale:.96;box-shadow:0 2px 0 #6f1224}
</style></head>
<body class="theme-love-letter">
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<main class="ll-letter">
  <div class="ll-seal"><svg><use href="#ic-heart"/></svg></div>
  <p class="ll-kicker"><?= $e($senderLabel) ?> requests your company</p>
  <?php if ($message): ?><p class="ll-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
<?php include __DIR__ . '/../../partials/analytics.php'; ?>
</body></html>

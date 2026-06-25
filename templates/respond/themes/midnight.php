<?php $message = $message ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($title ?? 'Crush') ?></title>
<style>
  *{box-sizing:border-box} body{margin:0;min-height:100svh;display:flex;align-items:flex-end;justify-content:center;
    background:radial-gradient(130% 80% at 70% 0%,#3a1a5e,#160c2e 55%,#0c0720);font-family:"Segoe UI",system-ui,sans-serif;color:#ede6ff;-webkit-font-smoothing:antialiased}
  .mn-star{position:fixed;width:3px;height:3px;background:#fff;border-radius:50%;opacity:.7}
  .mn-card{width:min(100vw,460px);margin:0 8px;background:rgba(255,255,255,.06);backdrop-filter:blur(12px);
    border:1px solid rgba(255,143,199,.25);border-radius:28px 28px 0 0;padding:26px 22px 28px;
    box-shadow:0 -10px 50px rgba(157,123,255,.4)}
  .mn-hero{height:96px;border-radius:20px;margin-bottom:14px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(120deg,#ff5fa2,#9d7bff);box-shadow:0 0 30px rgba(157,123,255,.6)}
  .mn-hero svg{width:44px;height:44px;color:#fff;filter:drop-shadow(0 0 10px rgba(255,255,255,.6))}
  .mn-title{font-size:24px;font-weight:800;text-wrap:balance;margin:4px 0;background:linear-gradient(90deg,#ff8fc7,#9d7bff);-webkit-background-clip:text;background-clip:text;color:transparent}
  .mn-msg{opacity:.85;line-height:1.5} .rf-error{color:#ff8fb0}
  .rf-form{display:flex;flex-direction:column;gap:14px;margin-top:14px} .rf-field{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:#b8a8e0}
  .rf-field input{padding:12px;border:1px solid rgba(255,143,199,.35);border-radius:14px;background:rgba(255,255,255,.06);color:#ede6ff;font-size:16px;font-family:inherit}
  .rf-meals{border:0;padding:0;margin:0} .rf-meals legend{font-weight:600;color:#b8a8e0;margin-bottom:8px}
  .rf-chips{display:flex;gap:10px;overflow-x:auto;padding-bottom:6px} .rf-chip{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:9px 14px;border-radius:999px;background:rgba(255,255,255,.08);color:#ffb3da;border:1px solid rgba(255,143,199,.4);cursor:pointer;white-space:nowrap;transition:scale .12s}
  .rf-chip:active{scale:.96} .rf-chip input{position:absolute;opacity:0;width:0;height:0} .rf-chip:has(input:checked){box-shadow:0 0 0 2px #ff5fa2 inset;background:rgba(255,95,162,.18)}
  .rf-ic{width:15px;height:15px} .rf-place{font-weight:700;color:#ff8fc7}
  .rf-cta{min-height:50px;border:0;border-radius:16px;background:linear-gradient(90deg,#ff5fa2,#9d7bff);color:#fff;font-weight:800;font-size:17px;font-family:inherit;cursor:pointer;box-shadow:0 0 24px rgba(157,123,255,.6);transition:scale .12s} .rf-cta:active{scale:.96}
</style></head>
<body class="theme-midnight">
<?php include __DIR__ . '/../../partials/icons.php'; ?>
<span class="mn-star" style="top:8%;left:18%"></span><span class="mn-star" style="top:14%;left:72%"></span>
<span class="mn-star" style="top:22%;left:44%"></span><span class="mn-star" style="top:6%;left:60%"></span>
<main class="mn-card">
  <div class="mn-hero"><svg><use href="#ic-moon"/></svg></div>
  <h1 class="mn-title"><?= $e($senderLabel) ?> has a crush on you</h1>
  <?php if ($message): ?><p class="mn-msg"><?= $e($message) ?></p><?php endif; ?>
  <?php include __DIR__ . '/../_form.php'; ?>
</main>
<?php include __DIR__ . '/../../partials/analytics.php'; ?>
</body></html>

<?php $lang = $lang ?? 'en'; ?>
<details class="lang-switch" style="position:fixed;top:10px;right:10px;z-index:40;">
  <summary style="list-style:none;cursor:pointer;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.85);box-shadow:0 2px 8px rgba(90,42,82,.18);display:flex;align-items:center;justify-content:center;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5a2a52" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
  </summary>
  <div style="position:absolute;right:0;margin-top:6px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(90,42,82,.2);padding:6px;min-width:150px;max-height:60vh;overflow:auto;">
    <?php foreach (\App\I18n\Languages::ALL as $code => $name): ?>
      <a href="/lang/<?= $e($code) ?>" style="display:block;padding:8px 12px;border-radius:8px;text-decoration:none;color:#5a2a52;font-weight:<?= $lang === $code ? '800' : '500' ?>;background:<?= $lang === $code ? '#faf2ff' : 'transparent' ?>;"><?= $e($name) ?></a>
    <?php endforeach; ?>
  </div>
</details>

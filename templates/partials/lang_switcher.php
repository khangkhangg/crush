<?php
$lang = $lang ?? 'en';
$currentLanguageName = \App\I18n\Languages::name((string) $lang);
?>
<details class="lang-switch" style="position:fixed;top:10px;right:10px;z-index:40;">
  <summary style="list-style:none;cursor:pointer;min-width:38px;min-height:38px;border-radius:999px;background:rgba(255,255,255,.88);box-shadow:0 2px 8px rgba(90,42,82,.18);display:flex;align-items:center;justify-content:center;gap:7px;padding:0 12px;color:#5a2a52;font-weight:800;font-size:13px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5a2a52" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
    <span><?= $e($currentLanguageName) ?></span>
  </summary>
  <div style="position:absolute;right:0;margin-top:6px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(90,42,82,.2);padding:6px;min-width:150px;max-height:60vh;overflow:auto;">
    <?php foreach (\App\I18n\Languages::ALL as $code => $languageName): ?>
      <a href="/lang/<?= $e($code) ?>" style="display:block;padding:8px 12px;border-radius:8px;text-decoration:none;color:#5a2a52;font-weight:<?= $lang === $code ? '800' : '500' ?>;background:<?= $lang === $code ? '#faf2ff' : 'transparent' ?>;"><?= $e($languageName) ?></a>
    <?php endforeach; ?>
  </div>
</details>

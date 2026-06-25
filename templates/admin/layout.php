<?php
$title = $title ?? 'Admin';
$adminUser = $adminUser ?? null;
$adminCsrf = $adminCsrf ?? '';
$adminName = is_array($adminUser) ? (string) ($adminUser['name'] ?? 'Admin') : 'Admin';
$adminEmail = is_array($adminUser) ? (string) ($adminUser['email'] ?? '') : '';
$adminInitial = strtoupper(substr(trim($adminName) !== '' ? trim($adminName) : ($adminEmail !== '' ? $adminEmail : 'A'), 0, 1));
$adminAvatar = is_array($adminUser) ? (string) ($adminUser['avatar_url'] ?? '') : '';
$adminAvatarKey = is_array($adminUser) ? (string) ($adminUser['avatar_key'] ?? '') : '';
$nav = [
    ['/admin', 'Dashboard', 'Overview'],
    ['/admin/settings', 'Settings', 'Configure'],
    ['/admin/themes', 'Themes', 'Experiments'],
    ['/admin/moderation', 'Moderation', 'Safety'],
    ['/admin/templates', 'Templates', 'Email'],
    ['/admin/share', 'Share', 'Channels'],
    ['/admin/languages', 'Languages', 'Copy'],
];
$active = static function (string $href, string $label) use ($title): bool {
    $needle = strtolower($label);
    $page = strtolower((string) $title);
    return $href === '/admin' ? in_array($page, ['admin', 'forbidden'], true) : str_contains($page, $needle);
};
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?></title>
<link rel="icon" href="/favicon.ico">
<style>
  :root{
    color-scheme:dark;
    --admin-bg:#05070d; --admin-panel:#0c111d; --admin-panel-2:#111827; --admin-ink:#f8fbff;
    --admin-muted:#8b98ad; --admin-line:rgba(255,255,255,.09); --admin-blue:#4f8cff;
    --admin-blue-2:#8bd3ff; --admin-pink:#ff4fa3; --admin-green:#3ddc97; --admin-warn:#f7c948;
    --admin-radius:20px; --admin-radius-sm:12px; --admin-shadow:0 24px 80px rgba(0,0,0,.38);
    --admin-ease:cubic-bezier(.2,0,0,1);
  }
  *{box-sizing:border-box}
  body{margin:0;min-height:100svh;background:
    radial-gradient(circle at 14% 10%, rgba(79,140,255,.24), transparent 30rem),
    radial-gradient(circle at 82% 0%, rgba(255,79,163,.16), transparent 24rem),
    linear-gradient(180deg,#070a12,#05070d 45%,#070b13);
    color:var(--admin-ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
    -webkit-font-smoothing:antialiased;letter-spacing:0}
  a{color:inherit}
  [data-admin-shell]{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100svh}
  .admin-sidebar{position:sticky;top:0;height:100svh;padding:22px 16px;border-right:1px solid var(--admin-line);
    background:linear-gradient(180deg,rgba(12,17,29,.92),rgba(5,7,13,.84));backdrop-filter:blur(18px)}
  .admin-brand{display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:24px}
  .admin-logo{width:42px;height:42px;border-radius:14px;background:linear-gradient(135deg,var(--admin-blue),var(--admin-pink));
    display:grid;place-items:center;box-shadow:0 14px 32px rgba(79,140,255,.24)}
  .admin-logo:before{content:"";width:18px;height:14px;border:2px solid #fff;border-radius:5px;box-shadow:0 0 0 5px rgba(255,255,255,.1)}
  .admin-brand strong{display:block;font-size:16px;line-height:1.1}.admin-brand span{display:block;color:var(--admin-muted);font-size:12px;margin-top:3px}
  .admin-nav{display:grid;gap:6px}.admin-nav a{display:grid;grid-template-columns:1fr auto;gap:8px;padding:11px 12px;border-radius:14px;
    text-decoration:none;color:#dbe7ff;transition:background .16s var(--admin-ease),color .16s var(--admin-ease),transform .16s var(--admin-ease)}
  .admin-nav a:hover{background:rgba(255,255,255,.07);transform:translateX(2px)}.admin-nav a[aria-current="page"]{background:linear-gradient(135deg,rgba(79,140,255,.2),rgba(139,211,255,.08));box-shadow:inset 0 0 0 1px rgba(79,140,255,.32)}
  .admin-nav small{color:var(--admin-muted);font-size:11px}.admin-nav b{font-size:14px}
  .admin-account{position:absolute;left:16px;right:16px;bottom:18px}
  .admin-account-card{display:flex;align-items:center;gap:10px;width:100%;min-height:58px;padding:10px;border-radius:16px;background:rgba(255,255,255,.055);box-shadow:inset 0 0 0 1px var(--admin-line);border:0;color:inherit;text-align:left;cursor:default}
  .admin-avatar{flex:0 0 auto;width:38px;height:38px;border-radius:13px;display:grid;place-items:center;background:linear-gradient(135deg,var(--admin-blue),var(--admin-pink));color:#fff;font-weight:950;overflow:hidden}
  .admin-avatar img{width:100%;height:100%;object-fit:cover}.admin-account-meta{min-width:0}.admin-account-meta strong{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.admin-account-meta span{display:block;margin-top:2px;color:var(--admin-muted);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .admin-account-menu{position:absolute;left:0;right:0;bottom:66px;display:grid;gap:6px;padding:8px;border-radius:16px;background:rgba(17,24,39,.98);box-shadow:0 18px 48px rgba(0,0,0,.44),inset 0 0 0 1px var(--admin-line);opacity:0;transform:translateY(8px);pointer-events:none;transition:opacity .16s var(--admin-ease),transform .16s var(--admin-ease)}
  .admin-account:hover .admin-account-menu,.admin-account:focus-within .admin-account-menu{opacity:1;transform:translateY(0);pointer-events:auto}
  .admin-account-menu a,.admin-account-menu button{display:flex;width:100%;align-items:center;justify-content:flex-start;min-height:38px;padding:9px 10px;border-radius:11px;background:transparent;box-shadow:none;color:#dbe7ff;text-decoration:none;font-weight:800;font-size:13px}
  .admin-account-menu a:hover,.admin-account-menu button:hover{background:rgba(255,255,255,.07);filter:none}.admin-account-menu form{margin:0}.admin-account-menu button{border:0;cursor:pointer}
  .admin-main{min-width:0;padding:24px}.admin-topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
  .admin-kicker{margin:0;color:var(--admin-blue-2);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}
  .admin-title{margin:4px 0 0;font-size:clamp(30px,4vw,48px);line-height:.98;text-wrap:balance}
  .admin-subtitle{margin:10px 0 0;color:var(--admin-muted);line-height:1.5;text-wrap:pretty;max-width:720px}
  .admin-status-pill{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:8px 12px;border-radius:999px;background:rgba(61,220,151,.1);color:#b9f7dc;font-weight:800;font-size:13px;box-shadow:inset 0 0 0 1px rgba(61,220,151,.22)}
  .admin-status-pill:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--admin-green);box-shadow:0 0 18px var(--admin-green)}
  .admin-content{display:grid;gap:18px}.panel{background:linear-gradient(180deg,rgba(17,24,39,.92),rgba(12,17,29,.96));border-radius:var(--admin-radius);padding:20px;box-shadow:var(--admin-shadow),inset 0 0 0 1px var(--admin-line)}
  .panel h1,.panel h2{margin:0 0 12px;text-wrap:balance}.panel h1{font-size:24px}.panel h2{font-size:18px}.panel p{color:var(--admin-muted);line-height:1.55;text-wrap:pretty}
  .admin-grid{display:grid;gap:16px}.admin-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
  .admin-stat{padding:16px;border-radius:16px;background:rgba(255,255,255,.055);box-shadow:inset 0 0 0 1px var(--admin-line)}
  .admin-stat span{display:block;color:var(--admin-muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
  .admin-stat strong{display:block;margin-top:8px;font-size:28px;font-variant-numeric:tabular-nums}
  label{display:grid;gap:7px;font-size:13px;font-weight:800;color:#d8e2f5;margin:0}
  input,select,textarea{width:100%;min-height:42px;padding:10px 12px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(3,6,14,.72);color:var(--admin-ink);font:inherit;outline:none;transition:border-color .16s var(--admin-ease),box-shadow .16s var(--admin-ease),background .16s var(--admin-ease)}
  textarea{line-height:1.5;resize:vertical}input:focus-visible,select:focus-visible,textarea:focus-visible{border-color:var(--admin-blue);box-shadow:0 0 0 4px rgba(79,140,255,.18)}
  input[type="checkbox"]{width:18px;min-height:18px;accent-color:var(--admin-blue)}button,.admin-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 14px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--admin-blue),#6f5cff);color:#fff;font-weight:900;font:inherit;cursor:pointer;text-decoration:none;transition:scale .12s var(--admin-ease),filter .16s var(--admin-ease)}
  button:active,.admin-btn:active{scale:.96}button:hover,.admin-btn:hover{filter:brightness(1.08)}
  .admin-btn--ghost{background:rgba(255,255,255,.07);box-shadow:inset 0 0 0 1px var(--admin-line);color:#dbe7ff}
  .admin-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px}.admin-section{display:grid;gap:12px;padding:16px;border-radius:16px;background:rgba(255,255,255,.045);box-shadow:inset 0 0 0 1px var(--admin-line)}
  .admin-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.admin-section-head p{margin:4px 0 0;font-size:13px}
  .admin-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-form-grid .span-2{grid-column:1/-1}
  .table-wrap{overflow:auto;border-radius:16px;box-shadow:inset 0 0 0 1px var(--admin-line)}table{width:100%;border-collapse:collapse;min-width:620px}td,th{text-align:left;padding:12px;border-bottom:1px solid var(--admin-line);font-size:14px;vertical-align:middle}th{color:#a9b8d0;font-size:12px;text-transform:uppercase;letter-spacing:.08em;background:rgba(255,255,255,.045)}td{color:#eef4ff}tr:last-child td{border-bottom:0}
  .flash{background:rgba(61,220,151,.1);border:1px solid rgba(61,220,151,.28);color:#b9f7dc;padding:12px 14px;border-radius:14px;margin:0 0 14px;font-weight:800}.flash--error{background:rgba(255,79,119,.12);border-color:rgba(255,79,119,.3);color:#ffc2d1}
  .admin-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;list-style:none;padding:0;margin:0}.admin-list a{display:block;padding:14px;border-radius:14px;background:rgba(255,255,255,.055);box-shadow:inset 0 0 0 1px var(--admin-line);text-decoration:none;font-weight:850}.admin-list small{display:block;margin-top:4px;color:var(--admin-muted)}
  @media (max-width:900px){[data-admin-shell]{display:block}.admin-sidebar{position:relative;height:auto;border-right:0;border-bottom:1px solid var(--admin-line)}.admin-nav{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-account{position:relative;left:auto;right:auto;bottom:auto;margin-top:14px}.admin-account-menu{position:static;margin-bottom:8px;opacity:1;transform:none;pointer-events:auto}.admin-grid--two,.admin-stat-grid,.admin-form-grid{grid-template-columns:1fr}.admin-main{padding:18px 12px}.admin-topbar{display:block}.admin-status-pill{margin-top:14px}}
  @media (max-width:520px){.admin-nav{grid-template-columns:1fr}.panel{padding:16px;border-radius:18px}.admin-title{font-size:32px}table{min-width:560px}}
  @media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style></head>
<body>
<div data-admin-shell>
  <aside class="admin-sidebar">
    <a class="admin-brand" href="/admin" aria-label="Crush admin dashboard">
      <span class="admin-logo" aria-hidden="true"></span>
      <span><strong>Crush Admin</strong><span>Configuration hub</span></span>
    </a>
    <nav class="admin-nav" aria-label="Admin navigation">
      <?php foreach ($nav as [$href, $label, $hint]): ?>
        <a href="<?= $e($href) ?>" <?= $active($href, $label) ? 'aria-current="page"' : '' ?>>
          <b><?= $e($label) ?></b><small><?= $e($hint) ?></small>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if (is_array($adminUser)): ?>
      <div class="admin-account" data-admin-account>
        <div class="admin-account-menu" role="menu" aria-label="Admin account menu">
          <a href="/profile" role="menuitem">Edit profile info</a>
          <a href="/profile/password" role="menuitem">Reset password</a>
          <form method="post" action="/logout">
            <input type="hidden" name="csrf" value="<?= $e($adminCsrf) ?>">
            <button type="submit" role="menuitem">Logout</button>
          </form>
        </div>
        <div class="admin-account-card" tabindex="0" aria-label="Admin account menu">
          <span class="admin-avatar" aria-hidden="true">
            <?php if ($adminAvatar !== ''): ?>
              <img src="<?= $e($adminAvatar) ?>" alt="">
            <?php elseif ($adminAvatarKey === 'custom' && isset($adminUser['id'])): ?>
              <img src="/avatar/<?= (int) $adminUser['id'] ?>" alt="">
            <?php else: ?>
              <?= $e($adminInitial) ?>
            <?php endif; ?>
          </span>
          <span class="admin-account-meta"><strong><?= $e($adminName) ?></strong><span><?= $e($adminEmail) ?></span></span>
        </div>
      </div>
    <?php endif; ?>
  </aside>
  <main class="admin-main">
    <header class="admin-topbar">
      <div>
        <p class="admin-kicker">Crush app admin</p>
        <h1 class="admin-title"><?= $e($title === 'Admin' ? 'Configuration hub' : $title) ?></h1>
        <p class="admin-subtitle">Tune the product experience from one darker, focused workspace: delivery, copy, themes, safety, and sharing.</p>
      </div>
      <span class="admin-status-pill">Live config</span>
    </header>
    <div class="admin-content">
      <?= $body ?>
    </div>
  </main>
</div>
<?php include __DIR__ . '/../partials/analytics.php'; ?>
</body></html>

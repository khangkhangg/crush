<?php $error = $error ?? null; $user = $user ?? []; $avatars = $avatars ?? []; ?>
<?php $content = function () use ($e, $error, $user, $avatars, $csrf) {
  $cur = $user['avatar_key'] ?? '';
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/avatars.php'; ?>
  <h1 style="text-wrap:balance;">Make it yours</h1>
  <p style="opacity:.8;margin-top:0;">A few cute details so your crush knows it's really you.</p>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/profile" style="display:flex;flex-direction:column;gap:14px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">Pick an avatar</legend>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
        <?php foreach ($avatars as $key): ?>
          <label style="cursor:pointer;">
            <input type="radio" name="avatar_key" value="<?= $e($key) ?>" <?= $cur === $key ? 'checked' : '' ?>
                   style="position:absolute;opacity:0;width:0;height:0;">
            <span class="av-pick" style="display:inline-flex;width:52px;height:52px;border-radius:16px;align-items:center;justify-content:center;background:#fff;box-shadow:0 0 0 2px <?= $cur === $key ? '#ff3d8b' : '#eadcff' ?> inset;">
              <svg width="34" height="34"><use href="#av-<?= $e($key) ?>"/></svg>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label style="font-size:13px;font-weight:600;opacity:.7;">Pronouns (optional)
      <input type="text" name="pronouns" value="<?= $e($user['pronouns'] ?? '') ?>" placeholder="she/her"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label style="font-size:13px;font-weight:600;opacity:.7;">About you
      <input type="text" name="bio" maxlength="280" value="<?= $e($user['bio'] ?? '') ?>" placeholder="i'm a sucker for tacos and bad puns"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label style="font-size:13px;font-weight:600;opacity:.7;">Contact (optional)
      <input type="text" name="contact" value="<?= $e($user['contact'] ?? '') ?>" placeholder="phone or @handle"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <button type="submit" style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">
      Save my profile
    </button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

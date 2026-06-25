<?php $user = $user ?? []; $avatars = $avatars ?? []; $returnTo = $returnTo ?? ''; $cur = $user['avatar_key'] ?? ''; ?>
<style>
  .av-grid { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
  .av-grid label { cursor:pointer; }
  .av-grid input { position:absolute; opacity:0; width:0; height:0; }
  .av-pick { display:inline-flex; width:52px; height:52px; border-radius:16px; align-items:center; justify-content:center; background:#fff; box-shadow:0 0 0 2px #eadcff inset; transition:box-shadow .15s, transform .15s; overflow:hidden; }
  .av-pick img { width:100%; height:100%; object-fit:cover; }
  .av-grid input:checked + .av-pick { box-shadow:0 0 0 3px #ff3d8b inset; transform:scale(1.06); }
  .av-grid input:focus-visible + .av-pick { outline:2px solid #ff8fc0; outline-offset:2px; }
</style>
<form method="post" action="/profile" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <input type="hidden" name="return_to" value="<?= $e($returnTo) ?>">
  <fieldset style="border:0;padding:0;margin:0;">
    <legend style="font-size:13px;font-weight:600;opacity:.7;">Pick an avatar</legend>
    <div class="av-grid">
      <?php if ($cur === 'custom'): ?>
        <label><input type="radio" name="avatar_key" value="custom" checked><span class="av-pick"><img src="/avatar/<?= (int) ($user['id'] ?? 0) ?>" alt="your photo"></span></label>
      <?php endif; ?>
      <?php foreach ($avatars as $key): ?>
        <label><input type="radio" name="avatar_key" value="<?= $e($key) ?>" <?= $cur === $key ? 'checked' : '' ?>><span class="av-pick"><svg width="34" height="34"><use href="#av-<?= $e($key) ?>"/></svg></span></label>
      <?php endforeach; ?>
    </div>
    <label style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#ff3d8b;cursor:pointer;">
      Upload your own photo
      <input type="file" name="avatar_file" accept="image/*" style="display:none;">
    </label>
  </fieldset>
  <label style="font-size:13px;font-weight:600;opacity:.7;">About you
    <input class="field" type="text" name="bio" maxlength="280" value="<?= $e($user['bio'] ?? '') ?>" placeholder="i'm a sucker for tacos and bad puns">
  </label>
  <label style="font-size:13px;font-weight:600;opacity:.7;">Contact (optional)
    <input class="field" type="text" name="contact" value="<?= $e($user['contact'] ?? '') ?>" placeholder="phone or @handle">
  </label>
  <button type="submit" style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;font-size:16px;cursor:pointer;">Save my profile</button>
</form>

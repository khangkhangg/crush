<?php $error = $error ?? null; $old = $old ?? []; $csrf = $csrf ?? ''; $meals = $meals ?? []; $me = $me ?? null; $cardClass = 'card--wide'; ?>
<?php $content = function () use ($e, $csrf, $error, $old, $meals, $me) {
  $val = fn(string $k) => $e($old[$k] ?? '');
  ob_start(); ?>
  <?php if ($me && !empty($me['name'])): ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:13px;background:#faf2ff;border:1px solid #eadcff;border-radius:12px;padding:8px 12px;margin-bottom:12px;">
      <?php if (!empty($me['avatar_key'])): ?>
        <?php include __DIR__ . '/../partials/avatars.php'; ?>
        <svg width="22" height="22"><use href="#av-<?= $e($me['avatar_key']) ?>"/></svg>
      <?php endif; ?>
      <span>Creating as <strong><?= $e($me['name']) ?></strong></span>
      <span style="margin-left:auto;opacity:.8;">not you? <a href="/switch" style="color:#ff3d8b;">use another email</a> · <a href="/login" style="color:#ff3d8b;">log in</a></span>
    </div>
  <?php endif; ?>
  <h1 style="text-wrap:balance;">Send a crush invite</h1>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/invites" style="display:flex;flex-direction:column;gap:12px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label>Their email
      <input type="email" name="crush_email" required value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>Their name (optional)
      <input type="text" name="crush_name" value="<?= $val('crush_name') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>A little message (optional)
      <textarea name="message" rows="3"
                style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;"><?= $val('message') ?></textarea>
    </label>
    <label>When should they pick?
      <select name="date_mode" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
        <option value="instant">Let them pick any time (final)</option>
        <option value="confirm">They propose, I confirm</option>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="is_anonymous" value="1"> Send anonymously (a secret admirer)
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="reveal_on_response" value="1"> Reveal me after they respond
    </label>
    <style>
      .iv-places { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
      .iv-place { display:grid; grid-template-columns:84px 1.4fr 1fr 1.4fr; gap:8px; align-items:center; }
      .iv-place > .iv-label { font-size:13px; opacity:.8; }
      .iv-place input { min-width:0; width:100%; padding:9px; border-radius:10px; border:1px solid #e7d4ff; }
      @media (max-width:560px) {
        .iv-place { grid-template-columns:1fr 1fr; }
        .iv-place > .iv-label { grid-column:1 / -1; font-weight:600; opacity:.7; }
        .iv-place .iv-url { grid-column:1 / -1; }
      }
    </style>
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">Suggest a spot for each vibe (optional)</legend>
      <div class="iv-places">
        <?php foreach (($meals ?? []) as $meal): ?>
          <div class="iv-place">
            <span class="iv-label"><?= $e($meal['label']) ?></span>
            <input type="text" name="places[<?= $e($meal['key']) ?>][name]" placeholder="restaurant name">
            <input type="text" name="places[<?= $e($meal['key']) ?>][cuisine]" placeholder="cuisine" list="cuisines">
            <input class="iv-url" type="text" name="places[<?= $e($meal['key']) ?>][url]" placeholder="maps link (optional)">
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <datalist id="cuisines">
      <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Mediterranean','BBQ','Vegan','Dessert'] as $c): ?>
        <option value="<?= $e($c) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <button type="submit"
            style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;">
      Create my invite
    </button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

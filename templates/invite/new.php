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
    <fieldset style="border:0;padding:0;margin:0;display:flex;gap:16px;flex-wrap:wrap;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">How will you send it?</legend>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="delivery" value="email" checked> Email it to them</label>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="delivery" value="link"> I will share the link myself</label>
    </fieldset>
    <div id="emailWrap" class="iv-collapse">
    <label>Their email
      <input type="email" id="crush_email" name="crush_email" value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    </div>
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
      .iv-collapse { overflow:hidden; transition:max-height .3s ease, opacity .3s ease; max-height:120px; opacity:1; }
      .iv-collapse.hide { max-height:0; opacity:0; }
      .iv-opt { display:grid; grid-template-columns:1.4fr 1fr 1.6fr auto; gap:8px; align-items:center; margin-top:8px; }
      .iv-opt input { min-width:0; width:100%; padding:9px; border-radius:10px; border:1px solid #e7d4ff; }
      .iv-opt .rm { border:0;background:none;color:#b3243b;cursor:pointer;font-size:18px;line-height:1; }
      .iv-prev { font-size:12px; color:#7a5; margin:2px 0 0 2px; min-height:14px; }
      @media (max-width:560px){ .iv-opt{ grid-template-columns:1fr 1fr; } .iv-opt .iv-u{ grid-column:1/-1; } }
      #placePanel.hide { display:none; }
    </style>
    <fieldset style="border:0;padding:0;margin:0;">
      <legend style="font-size:13px;font-weight:600;opacity:.7;">A spot to suggest?</legend>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="place_mode" value="open" checked> I'm open — they pick the vibe</label>
      <label style="display:flex;align-items:center;gap:6px;"><input type="radio" name="place_mode" value="focused"> Let's do a specific vibe</label>
      <div id="placePanel" class="hide" style="margin-top:8px;">
        <select name="focus_vibe" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
          <?php foreach (($meals ?? []) as $meal): ?>
            <option value="<?= $e($meal['key']) ?>"><?= $e($meal['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="optList">
          <div class="iv-opt">
            <input type="text" name="opts[0][name]" placeholder="restaurant name">
            <input type="text" name="opts[0][cuisine]" placeholder="cuisine" list="cuisines">
            <input class="iv-u" type="text" name="opts[0][url]" placeholder="maps link (optional)" data-maps>
            <button type="button" class="rm" aria-label="Remove">&times;</button>
          </div>
        </div>
        <button type="button" id="addPlace" style="margin-top:6px;padding:8px 12px;border:1px dashed #e7d4ff;border-radius:10px;background:#fff;color:#ff3d8b;font-weight:600;cursor:pointer;">+ Add another place</button>
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
    <script>
    (function(){
      var email = document.getElementById('crush_email');
      var wrap = document.getElementById('emailWrap');
      function syncDelivery(){
        var m = document.querySelector('input[name="delivery"]:checked');
        var link = m && m.value === 'link';
        if (email) email.required = !link;
        if (wrap) wrap.classList.toggle('hide', !!link);
      }
      document.querySelectorAll('input[name="delivery"]').forEach(function(r){ r.addEventListener('change', syncDelivery); });
      syncDelivery();

      var panel = document.getElementById('placePanel');
      function syncMode(){
        var m = document.querySelector('input[name="place_mode"]:checked');
        if (panel) panel.classList.toggle('hide', !(m && m.value === 'focused'));
      }
      document.querySelectorAll('input[name="place_mode"]').forEach(function(r){ r.addEventListener('change', syncMode); });
      syncMode();

      var list = document.getElementById('optList');
      var add = document.getElementById('addPlace');
      if (add && list) add.addEventListener('click', function(){
        var n = list.children.length;
        var row = list.children[0].cloneNode(true);
        row.querySelectorAll('input').forEach(function(inp){
          inp.value = '';
          inp.name = inp.name.replace(/opts\[\d+\]/, 'opts[' + n + ']');
        });
        var pv = row.querySelector('.iv-prev'); if (pv) pv.remove();
        list.appendChild(row);
      });
      if (list) list.addEventListener('click', function(e){
        if (e.target.classList.contains('rm') && list.children.length > 1) e.target.closest('.iv-opt').remove();
      });
    })();
    </script>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

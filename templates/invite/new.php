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
    <div>
      <span class="label">How will you send it?</span>
      <div class="seg" role="radiogroup" aria-label="How will you send it?">
        <label><input type="radio" name="delivery" value="email" checked><span>Email it to them</span></label>
        <label><input type="radio" name="delivery" value="link"><span>I'll share the link</span></label>
      </div>
    </div>
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
    <div>
      <span class="label">When should they pick?</span>
      <div class="seg" role="radiogroup" aria-label="When should they pick?">
        <label><input type="radio" name="date_mode" value="instant" checked><span>Any time (final)</span></label>
        <label><input type="radio" name="date_mode" value="confirm"><span>They propose, I confirm</span></label>
      </div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="is_anonymous" value="1"> Send anonymously (a secret admirer)
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="reveal_on_response" value="1"> Reveal me after they respond
    </label>
    <style>
      .iv-collapse { overflow:hidden; transition:max-height .3s ease, opacity .3s ease; max-height:120px; opacity:1; }
      .iv-collapse.hide { max-height:0; opacity:0; }
      .iv-opt { border:1px solid #eadcff; border-radius:14px; padding:10px; margin-top:8px; background:#fdfaff; }
      .iv-opt-top { display:grid; grid-template-columns:1.5fr 1.5fr auto; gap:8px; align-items:center; }
      .iv-opt-top input { min-width:0; }
      .iv-opt .rm { border:0; background:none; color:#b3243b; cursor:pointer; font-size:18px; line-height:1; }
      .iv-opt .chips { margin-top:8px; }
      .iv-other { margin-top:6px; }
      .iv-prev { font-size:12px; color:#7a5; margin:6px 0 0 2px; min-height:14px; }
      @media (max-width:560px){ .iv-opt-top { grid-template-columns:1fr auto; } .iv-opt-top .iv-u { grid-column:1 / -1; } }
      #placePanel.hide { display:none; }
    </style>
    <fieldset style="border:0;padding:0;margin:0;">
      <span class="label">A spot to suggest?</span>
      <div class="seg" role="radiogroup" aria-label="A spot to suggest?">
        <label><input type="radio" name="place_mode" value="open" checked><span>I'm open — they pick</span></label>
        <label><input type="radio" name="place_mode" value="focused"><span>Let's do a vibe</span></label>
      </div>
      <div id="placePanel" class="hide" style="margin-top:8px;">
        <select name="focus_vibe" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
          <?php foreach (($meals ?? []) as $meal): ?>
            <option value="<?= $e($meal['key']) ?>"><?= $e($meal['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="optList">
          <div class="iv-opt">
            <div class="iv-opt-top">
              <input class="field" type="text" name="opts[0][name]" placeholder="restaurant name">
              <input class="field iv-u" type="text" name="opts[0][url]" placeholder="maps link (optional)" data-maps>
              <button type="button" class="rm" aria-label="Remove">&times;</button>
            </div>
            <div class="chips">
              <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Dessert'] as $c): ?>
                <label class="chip"><input type="radio" name="opts[0][cuisine]" value="<?= $e($c) ?>"><span><?= $e($c) ?></span></label>
              <?php endforeach; ?>
              <label class="chip"><input type="radio" name="opts[0][cuisine]" value="__other__" data-other><span>Other</span></label>
            </div>
            <input class="field iv-other" type="text" name="opts[0][cuisine_custom]" placeholder="cuisine" hidden>
          </div>
        </div>
        <button type="button" id="addPlace" style="margin-top:6px;padding:8px 12px;border:1px dashed #e7d4ff;border-radius:10px;background:#fff;color:#ff3d8b;font-weight:600;cursor:pointer;">+ Add another place</button>
      </div>
    </fieldset>
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
      function syncOther(scope){
        (scope || document).querySelectorAll('.iv-opt').forEach(function(opt){
          var other = opt.querySelector('input[data-other]');
          var field = opt.querySelector('.iv-other');
          if (field) field.hidden = !(other && other.checked);
        });
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.name && /\[cuisine\]$/.test(e.target.name)) syncOther(e.target.closest('.iv-opt'));
      });
      syncOther();
      if (add && list) add.addEventListener('click', function(){
        var n = list.children.length;
        var row = list.children[0].cloneNode(true);
        row.querySelectorAll('input').forEach(function(inp){
          if (inp.type === 'radio') inp.checked = false; else inp.value = '';
          inp.name = inp.name.replace(/opts\[\d+\]/, 'opts[' + n + ']');
        });
        var field = row.querySelector('.iv-other'); if (field) field.hidden = true;
        var pv = row.querySelector('.iv-prev'); if (pv) pv.remove();
        list.appendChild(row);
      });
      if (list) list.addEventListener('click', function(e){
        if (e.target.classList.contains('rm') && list.children.length > 1) e.target.closest('.iv-opt').remove();
      });

      function showPrev(input){
        var url = (input.value || '').trim();
        var row = input.closest('.iv-opt');
        var prev = row && row.querySelector('.iv-prev');
        if (!prev) { prev = document.createElement('div'); prev.className = 'iv-prev'; row && row.appendChild(prev); }
        if (!url) { prev.textContent = ''; return; }
        prev.textContent = 'Looking up…';
        fetch('/maps/preview?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } })
          .then(function(r){ return r.json(); })
          .then(function(d){ prev.textContent = d && (d.name || d.address) ? ('Found: ' + (d.name || d.address)) : ''; })
          .catch(function(){ prev.textContent = ''; });
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-maps')) showPrev(e.target);
      });
    })();
    </script>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

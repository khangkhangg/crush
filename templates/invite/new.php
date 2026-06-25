<?php $error = $error ?? null; $old = $old ?? []; $csrf = $csrf ?? ''; $meals = $meals ?? []; $me = $me ?? null; $cardClass = 'card--quest'; ?>
<?php $content = function () use ($e, $t, $csrf, $error, $old, $meals, $me) {
  $val = fn(string $k) => $e($old[$k] ?? '');
  ob_start(); ?>
  <?php include __DIR__ . '/../partials/icons.php'; ?>
  <div class="quest-grid invite-builder" data-redesign-page="invite-builder">
    <section class="quest-main">
      <?php if ($me && !empty($me['name'])): ?>
        <div class="identity-pill">
          <?php if (!empty($me['avatar_key'])): ?>
            <?php include __DIR__ . '/../partials/avatars.php'; ?>
            <svg width="24" height="24"><use href="#av-<?= $e($me['avatar_key']) ?>"/></svg>
          <?php else: ?>
            <span class="identity-dot" aria-hidden="true"></span>
          <?php endif; ?>
          <span><?= $e($t('Creating as')) ?> <strong><?= $e($me['name']) ?></strong></span>
          <span class="identity-links"><a href="/switch"><?= $e($t('use another email')) ?></a><a href="/login"><?= $e($t('log in')) ?></a></span>
        </div>
      <?php endif; ?>

      <p class="eyebrow"><?= $e($t('Date quest')) ?></p>
      <h1 class="title"><?= $e($t('Send a crush invite')) ?></h1>
      <div class="quest-progress" aria-hidden="true"><span class="on"></span><span class="on"></span><span></span><span></span></div>
      <?php if ($error): ?><p role="alert" class="form-error"><?= $e($error) ?></p><?php endif; ?>

      <form method="post" action="/invites" class="invite-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <section class="quest-step">
          <div class="step-head">
            <span class="step-no">1</span>
            <div><h2><?= $e($t('Who is it for?')) ?></h2><p><?= $e($t('Keep it private until you are ready.')) ?></p></div>
          </div>
          <span class="label"><?= $e($t('How will you send it?')) ?></span>
          <div class="seg" role="radiogroup" aria-label="<?= $e($t('How will you send it?')) ?>">
            <label><input type="radio" name="delivery" value="email" checked><span><?= $e($t('Email it to them')) ?></span></label>
            <label><input type="radio" name="delivery" value="link"><span><?= $e($t('I will share the link')) ?></span></label>
          </div>
          <div id="emailWrap" class="iv-collapse">
            <label class="label-block"><?= $e($t('Their email')) ?>
              <input class="field" type="email" id="crush_email" name="crush_email" value="<?= $val('crush_email') ?>">
            </label>
          </div>
          <label class="label-block"><?= $e($t('Their name')) ?>
            <input class="field" type="text" name="crush_name" required value="<?= $val('crush_name') ?>">
          </label>
          <label class="label-block"><?= $e($t('A little message')) ?> <span><?= $e($t('optional')) ?></span>
            <textarea class="field" name="message" rows="3"><?= $val('message') ?></textarea>
          </label>
        </section>

        <section class="quest-step">
          <div class="step-head">
            <span class="step-no">2</span>
            <div><h2><?= $e($t('Secret mode')) ?></h2><p><?= $e($t('Choose how much to reveal.')) ?></p></div>
          </div>
          <div class="toggle-cards">
            <label class="toggle-card">
              <input type="checkbox" name="is_anonymous" value="1">
              <span><strong><?= $e($t('Secret admirer')) ?></strong><small><?= $e($t('Send anonymously.')) ?></small></span>
            </label>
            <label class="toggle-card">
              <input type="checkbox" name="reveal_on_response" value="1">
              <span><strong><?= $e($t('Reveal after answer')) ?></strong><small><?= $e($t('Let them know it is you later.')) ?></small></span>
            </label>
          </div>
          <span class="label"><?= $e($t('When should they pick?')) ?></span>
          <div class="seg" role="radiogroup" aria-label="<?= $e($t('When should they pick?')) ?>">
            <label><input type="radio" name="date_mode" value="instant" checked><span><?= $e($t('Let them choose')) ?></span></label>
            <label><input type="radio" name="date_mode" value="confirm"><span><?= $e($t('I want to approve')) ?></span></label>
          </div>
        </section>

        <section class="quest-step">
          <div class="step-head">
            <span class="step-no">3</span>
            <div><h2><?= $e($t('Add a vibe')) ?></h2><p><?= $e($t('Optional, but it makes the invite easier to answer.')) ?></p></div>
          </div>
          <span class="label"><?= $e($t('A spot to suggest?')) ?></span>
          <div class="seg" role="radiogroup" aria-label="<?= $e($t('A spot to suggest?')) ?>">
            <label><input type="radio" name="place_mode" value="open" checked><span><?= $e($t('They pick')) ?></span></label>
            <label><input type="radio" name="place_mode" value="focused"><span><?= $e($t('Suggest a vibe')) ?></span></label>
          </div>
          <div id="placePanel" class="place-panel">
            <select name="focus_vibe" id="focusVibe" class="field">
              <?php foreach (($meals ?? []) as $meal): ?>
                <option value="<?= $e($meal['key']) ?>"><?= $e($t($meal['label'])) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="optList">
              <div class="iv-opt">
                <div class="iv-opt-top">
                  <input class="field iv-name" type="text" name="opts[0][name]" placeholder="<?= $e($t('restaurant name')) ?>" data-restaurant-placeholder="<?= $e($t('restaurant name')) ?>" data-hotel-placeholder="<?= $e($t('hotel name')) ?>">
                  <input class="field iv-u" type="text" name="opts[0][url]" placeholder="<?= $e($t('maps link (optional)')) ?>" data-maps>
                  <button type="button" class="rm" aria-label="<?= $e($t('Remove')) ?>">&times;</button>
                </div>
                <div class="chips" data-cuisine-controls>
                  <?php foreach (['Italian','Japanese','Korean','Vietnamese','Thai','Chinese','Mexican','Indian','American','French','Dessert'] as $c): ?>
                    <label class="chip"><input type="radio" name="opts[0][cuisine]" value="<?= $e($c) ?>"><span><?= $e($c) ?></span></label>
                  <?php endforeach; ?>
                  <label class="chip"><input type="radio" name="opts[0][cuisine]" value="__other__" data-other><span><?= $e($t('Other')) ?></span></label>
                </div>
                <input class="field iv-other" type="text" name="opts[0][cuisine_custom]" placeholder="<?= $e($t('cuisine')) ?>" data-cuisine-controls hidden>
              </div>
            </div>
            <button type="button" id="addPlace" class="btn btn--ghost add-place"><?= $e($t('Add another place')) ?></button>
          </div>
        </section>

        <button type="submit" class="btn submit-invite"><?= $e($t('Create my invite')) ?></button>
      </form>
    </section>

    <aside class="quest-side invite-preview" aria-hidden="true">
      <div class="mini-scene"><img class="generated-art generated-art--float generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"></div>
      <div class="soft-panel preview-panel">
        <p class="preview-label"><?= $e($t('Invite preview')) ?></p>
        <div class="preview-name"><?= $e($t('A private date quest')) ?></div>
        <img class="vibe-sheet" src="/assets/generated/vibe-stickers.png" alt="" loading="lazy" decoding="async">
        <div class="preview-lines"><span></span><span></span><span></span></div>
        <div class="preview-status"><span></span><span></span><span></span></div>
      </div>
    </aside>
  </div>

  <div id="mapModal" class="map-modal" aria-hidden="true">
    <div class="card2" role="dialog" aria-label="<?= $e($t('Map preview')) ?>">
      <div class="bar"><strong id="mapTitle"><?= $e($t('Map preview')) ?></strong><button type="button" class="x" id="mapClose" aria-label="<?= $e($t('Close')) ?>">&times;</button></div>
      <div id="mapFrame"></div>
    </div>
  </div>

  <style>
    .invite-builder { align-items:start; }
    .identity-pill { display:flex; align-items:center; gap:9px; border-radius:18px; background:#faf2ff; box-shadow:var(--shadow-soft); padding:10px 12px; margin-bottom:16px; font-size:13px; }
    .identity-dot { width:24px;height:24px;border-radius:50%;background:linear-gradient(150deg,#ff3d8b,#9d7bff); }
    .identity-links { margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
    .identity-links a { color:#ff3d8b; font-weight:800; text-decoration:none; }
    .form-error { color:#b3243b; background:#fff0f4; border-radius:16px; padding:11px 13px; }
    .invite-form { display:grid; gap:16px; }
    .quest-progress { display:flex; gap:10px; margin:8px 0 18px; }
    .quest-progress span { width:42px; height:8px; border-radius:999px; background:#eadcff; box-shadow:inset 0 0 0 1px rgba(255,255,255,.72); }
    .quest-progress span.on { background:linear-gradient(90deg,#ff3d8b,#ff8fc0); }
    .quest-step { display:grid; gap:12px; padding:16px; border-radius:24px; background:linear-gradient(180deg,#fff,#fff8fc); box-shadow:var(--shadow-soft); }
    .step-head { display:flex; align-items:flex-start; gap:12px; }
    .step-no { flex:0 0 auto; width:34px; height:34px; border-radius:13px; display:grid; place-items:center; background:var(--pink); color:#fff; font-weight:950; box-shadow:0 6px 0 #c81e68; }
    .step-head h2 { margin:0; font-size:19px; line-height:1.15; text-wrap:balance; }
    .step-head p { margin:4px 0 0; color:var(--muted); font-size:13px; line-height:1.35; text-wrap:pretty; }
    .label-block { display:grid; gap:7px; color:var(--muted); font-size:13px; font-weight:800; }
    .label-block span { font-weight:700; opacity:.72; }
    .iv-collapse { overflow:hidden; transition:max-height .3s var(--ease), opacity .24s var(--ease), margin .24s var(--ease); max-height:96px; opacity:1; }
    .iv-collapse.hide { max-height:0; opacity:0; margin-top:-10px; }
    .toggle-cards { display:grid; grid-template-columns:1fr; gap:9px; }
    .toggle-card { position:relative; cursor:pointer; }
    .toggle-card input { position:absolute; opacity:0; }
    .toggle-card span { display:flex; justify-content:space-between; gap:12px; min-height:58px; border-radius:18px; border:1.5px solid var(--lilac); background:#fff; padding:12px 14px; transition:box-shadow .15s var(--ease), border-color .15s var(--ease), scale .12s var(--ease); }
    .toggle-card span:active { scale:.96; }
    .toggle-card small { color:var(--muted); font-weight:700; text-align:right; }
    .toggle-card input:checked + span { border-color:var(--pink); box-shadow:0 0 0 3px rgba(255,61,139,.12),0 8px 20px rgba(255,61,139,.12); }
    .place-panel { overflow:hidden; max-height:0; opacity:0; transition:max-height .35s var(--ease), opacity .3s var(--ease); display:grid; gap:10px; }
    #placePanel.show { max-height:1600px; opacity:1; }
    .iv-opt { border:1px solid #eadcff; border-radius:20px; padding:12px; background:#fdfaff; box-shadow:var(--shadow-soft); }
    .iv-opt-top { display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:center; }
    .iv-opt .rm { width:42px;height:42px;border:0;border-radius:14px;background:#fff0f4;color:#b3243b;cursor:pointer;font-size:21px;line-height:1; }
    .iv-opt .chips { margin-top:10px; }
    .iv-other { margin-top:8px; }
    .iv-prev { font-size:12px; color:#6f7f3a; margin:8px 0 0 2px; min-height:14px; }
    .add-place { justify-self:start; box-shadow:none; }
    .submit-invite { width:100%; }
    .invite-preview { display:none; position:sticky; top:30px; align-self:start; }
    .preview-panel { margin-top:16px; }
    .preview-label { margin:0 0 8px; color:var(--pink); font-weight:900; font-size:13px; }
    .preview-name { font-size:24px; font-weight:950; text-wrap:balance; }
    .vibe-sheet { display:block; width:100%; max-width:260px; margin:14px auto 4px; border-radius:20px; filter:drop-shadow(0 12px 20px rgba(90,42,82,.12)); }
    .preview-lines { display:grid; gap:9px; margin:18px 0; }
    .preview-lines span, .preview-status span { display:block; height:12px; border-radius:999px; background:#f4ecff; }
    .preview-lines span:nth-child(2) { width:82%; } .preview-lines span:nth-child(3) { width:58%; background:#ffe3f1; }
    .preview-status { display:grid; grid-template-columns:repeat(3,1fr); gap:7px; }
    .preview-status span { height:8px; background:linear-gradient(90deg,#ff3d8b,#ff8fc0); }
    .map-modal { position:fixed; inset:0; background:rgba(60,30,70,.45); display:none; align-items:center; justify-content:center; z-index:50; padding:16px; }
    .map-modal.show { display:flex; }
    .map-modal .card2 { background:#fff; border-radius:22px; padding:10px; width:min(92vw,560px); box-shadow:0 20px 50px rgba(90,42,82,.35); }
    .map-modal .bar { display:flex; align-items:center; justify-content:space-between; padding:4px 6px 8px; }
    .map-modal .bar strong { font-size:14px; }
    .map-modal .x { border:0; background:none; font-size:22px; line-height:1; cursor:pointer; color:#7a5e86; }
    .map-modal iframe { width:100%; height:300px; border:0; border-radius:16px; display:block; }
    @media (min-width:760px) { .invite-preview { display:block; } .toggle-cards { grid-template-columns:1fr 1fr; } }
    @media (max-width:560px){ .iv-opt-top { grid-template-columns:1fr auto; } .iv-opt-top .iv-u { grid-column:1 / -1; } .identity-pill { align-items:flex-start; } .identity-links { width:100%; margin-left:0; } }
    @media (prefers-reduced-motion: reduce) { .iv-collapse, .place-panel { transition:none; } }
  </style>

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
      var show = !!(m && m.value === 'focused');
      if (panel) {
        panel.classList.toggle('show', show);
        panel.querySelectorAll('input, select, button').forEach(function(el){ el.disabled = !show; });
      }
    }
    document.querySelectorAll('input[name="place_mode"]').forEach(function(r){ r.addEventListener('change', syncMode); });
    syncMode();

    var list = document.getElementById('optList');
    var add = document.getElementById('addPlace');
    var vibe = document.getElementById('focusVibe');
    function syncVibe(){
      var hotel = !!(vibe && vibe.value === 'hotel');
      if (add) add.hidden = hotel;
      if (list) Array.prototype.slice.call(list.children).forEach(function(opt, i){
        if (hotel) {
          if (i > 0) opt.remove();
        }
      });
      if (list) list.querySelectorAll('.iv-opt').forEach(function(opt){
        var name = opt.querySelector('.iv-name');
        if (name) name.placeholder = hotel ? (name.getAttribute('data-hotel-placeholder') || 'hotel name') : (name.getAttribute('data-restaurant-placeholder') || 'restaurant name');
        opt.querySelectorAll('[data-cuisine-controls]').forEach(function(el){ el.hidden = hotel; });
        opt.querySelectorAll('input[name$="[cuisine]"]').forEach(function(inp){ if (hotel) inp.checked = false; });
        var custom = opt.querySelector('.iv-other'); if (custom && hotel) custom.value = '';
        var btn = opt.querySelector('.rm'); if (btn) btn.hidden = hotel;
      });
    }
    if (vibe) vibe.addEventListener('change', syncVibe);
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
      syncVibe();
    });
    if (list) list.addEventListener('click', function(e){
      if (e.target.classList.contains('rm') && list.children.length > 1) e.target.closest('.iv-opt').remove();
    });

    var modal = document.getElementById('mapModal');
    var frame = document.getElementById('mapFrame');
    var mapTitle = document.getElementById('mapTitle');
    function openMap(place){
      if (!modal || !frame) return;
      frame.innerHTML = '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=' + encodeURIComponent(place) + '&output=embed"></iframe>';
      if (mapTitle) mapTitle.textContent = place;
      modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false');
    }
    function closeMap(){
      if (!modal || !frame) return;
      modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); frame.innerHTML = '';
    }
    var mapClose = document.getElementById('mapClose');
    if (mapClose) mapClose.addEventListener('click', closeMap);
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeMap(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMap(); });

    function showPrev(input){
      var url = (input.value || '').trim();
      var row = input.closest('.iv-opt');
      var prev = row && row.querySelector('.iv-prev');
      if (!prev) { prev = document.createElement('div'); prev.className = 'iv-prev'; row && row.appendChild(prev); }
      if (!url) { prev.textContent = ''; return; }
      prev.textContent = 'Looking up...';
      fetch('/maps/preview?url=' + encodeURIComponent(url), { headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(d){
          var place = d && (d.address || d.name);
          if (!place) { prev.textContent = ''; return; }
          prev.textContent = 'Found: ' + place + '  ';
          if (window.innerWidth >= 700) {
            var btn = document.createElement('button');
            btn.type = 'button'; btn.textContent = 'View map';
            btn.style.cssText = 'border:0;background:none;color:#ff3d8b;font-weight:700;cursor:pointer;padding:0;';
            btn.addEventListener('click', function(){ openMap(place); });
            prev.appendChild(btn);
            openMap(place);
          }
        })
        .catch(function(){ prev.textContent = ''; });
    }
    document.addEventListener('change', function(e){
      if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-maps')) showPrev(e.target);
    });
    syncVibe();
  })();
  </script>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

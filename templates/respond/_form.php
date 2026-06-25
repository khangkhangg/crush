<?php $error = $error ?? null; $meals = $meals ?? []; $places = $places ?? []; ?>
<?php $collapseMeal = $collapseMeal ?? null; $focusVibe = $focusVibe ?? null; $focusOptions = $focusOptions ?? []; ?>
<?php if ($error): ?><p class="rf-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/i/<?= $e($token) ?>" class="rf-form">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <label class="rf-field"><?= $e($t('Pick a day')) ?>
    <input type="date" name="chosen_date" required>
  </label>
  <div class="rf-tod" role="group" aria-label="<?= $e($t('Time of day')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin:2px 0 6px;">
    <button type="button" class="rf-tod-b" data-time="09:00"><?= $e($t('Morning')) ?></button>
    <button type="button" class="rf-tod-b" data-time="14:00"><?= $e($t('Afternoon')) ?></button>
    <button type="button" class="rf-tod-b" data-time="19:00"><?= $e($t('Evening')) ?></button>
  </div>
  <label class="rf-field"><?= $e($t('at')) ?>
    <input type="time" name="chosen_time" required>
  </label>
<?php if ($focusVibe !== null): ?>
  <input type="hidden" name="meal_choice" value="<?= $e($focusVibe['key']) ?>">
  <fieldset class="rf-meals">
    <legend><?= $e($t($focusVibe['label'])) ?> — <?= $e($t('pick a spot')) ?></legend>
    <div class="rf-chips">
      <?php foreach ($focusOptions as $opt):
        $oname = $opt['place_resolved_name'] ?: $opt['place_name'];
        $ocuisine = $opt['cuisine'] ?? ''; $omap = $opt['place_clean_url'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="chosen_place" value="<?= $e($opt['id']) ?>" data-place="<?= $e($oname) ?>">
          <span><?= $e($oname) ?><?php if ($ocuisine !== ''): ?> · <?= $e($ocuisine) ?><?php endif; ?></span>
          <?php if (is_string($omap) && str_starts_with((string) $omap, 'http')): ?>
            <a href="<?= $e($omap) ?>" target="_blank" rel="noopener" style="font-size:11px;"><?= $e($t('map')) ?></a>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($collapseMeal !== null):
        $cp = $places[$collapseMeal['key']] ?? null;
        $cspot = $cp ? ($cp['place_resolved_name'] ?: $cp['place_name']) : '';
        $ccuisine = $cp['cuisine'] ?? null; ?>
  <input type="hidden" name="meal_choice" value="<?= $e($collapseMeal['key']) ?>">
  <div class="rf-collapsed">
    <?php if ($ccuisine): ?><strong class="rf-cuisine"><?= $e($ccuisine) ?></strong><?php endif; ?>
    <span><?= $e($t($collapseMeal['label'])) ?><?php if ($cspot !== ''): ?> <?= $e($t('at')) ?> <?= $e($cspot) ?><?php endif; ?></span>
  </div>
<?php else: ?>
  <fieldset class="rf-meals">
    <legend><?= $e($t('Pick a vibe')) ?></legend>
    <div class="rf-chips">
      <?php foreach ($meals as $m): $p = $places[$m['key']] ?? null;
            $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : '';
            $pcuisine = $p['cuisine'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>"
                 data-place="<?= $e($plabel) ?>" data-cuisine="<?= $e($pcuisine) ?>" data-label="<?= $e($m['label']) ?>">
          <svg class="rf-ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($t($m['label'])) ?><?php if ($pcuisine !== ''): ?> · <?= $e($pcuisine) ?><?php endif; ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php endif; ?>
  <p class="rf-place" style="display:none"></p>
  <label class="rf-field"><?= $e($t('Any wish? (optional)')) ?>
    <input type="text" name="meal_wish" placeholder="<?= $e($t('surprise me')) ?>">
  </label>
  <label class="rf-field"><?= $e($t('Your contact (optional)')) ?>
    <input type="text" name="crush_contact" placeholder="<?= $e($t('phone or @handle')) ?>">
  </label>
  <label class="rf-field"><?= $e($t('Where should they pick you up? (optional)')) ?>
    <input type="text" name="pickup_raw" placeholder="<?= $e($t('address or Google Maps link')) ?>" data-maps="1">
  </label>
  <button type="submit" class="rf-cta"><?= $e($t('Send my answer')) ?></button>
</form>
<div id="rfMapModal" class="rf-map-modal" aria-hidden="true">
  <div class="rf-map-card" role="dialog" aria-label="Map">
    <button type="button" class="rf-map-x" aria-label="Close">&times;</button>
    <div id="rfMapFrame"></div>
  </div>
</div>
<style>
  .rf-form{position:relative}.rf-form:before{content:"";display:block;width:100%;height:8px;border-radius:999px;background:linear-gradient(90deg,#ff3d8b 35%,#e7d4ff 35%);margin:0 0 8px}
  .rf-tod-b{min-height:44px;border:1px solid rgba(255,61,139,.24);border-radius:999px;background:#fff;color:inherit;font-weight:800;padding:8px 14px;cursor:pointer;transition:transform .15s,box-shadow .15s}.rf-tod-b:active{scale:.96}.rf-tod-b[data-on]{background:#ff3d8b;color:#fff;box-shadow:0 8px 18px rgba(255,61,139,.22)}
  .rf-field input{outline:none;transition:border-color .15s,box-shadow .15s,transform .15s}.rf-field input:focus-visible{border-color:#ff8fc0!important;box-shadow:0 0 0 4px rgba(255,143,192,.18);transform:translateY(-1px)}
  .rf-collapsed{border-radius:18px;padding:12px 14px;background:rgba(255,255,255,.7);box-shadow:0 10px 24px rgba(90,42,82,.08);display:flex;gap:8px;flex-wrap:wrap}
  .rf-cta{width:100%}
  .rf-map-modal { position:fixed; inset:0; background:rgba(40,20,50,.4); display:none; z-index:60; }
  .rf-map-modal.show { display:block; }
  .rf-map-card { position:absolute; background:#fff; box-shadow:0 20px 50px rgba(0,0,0,.3); }
  .rf-map-card iframe { width:100%; height:100%; border:0; display:block; }
  .rf-map-x { position:absolute; top:6px; right:8px; z-index:2; border:0; background:#fff; border-radius:50%; width:30px; height:30px; font-size:20px; line-height:1; cursor:pointer; }
  /* mobile: centered popover */
  .rf-map-card { left:50%; top:50%; transform:translate(-50%,-50%); width:92vw; max-width:520px; height:60vh; border-radius:16px; }
  .rf-map-card iframe { border-radius:16px; }
  /* desktop: slide in from the right */
  @media (min-width:760px) {
    .rf-map-card { left:auto; right:0; top:0; transform:translateX(100%); width:min(46vw,520px); height:100%; border-radius:18px 0 0 18px; transition:transform .3s ease; }
    .rf-map-modal.show .rf-map-card { transform:translateX(0); }
  }
  @media (max-width:520px){ .rf-cta{width:calc(100% - 58px)} }
  @media (prefers-reduced-motion: reduce) { .rf-map-card { transition:none; } }
</style>
<script>
(function(){
  var timeInput = document.querySelector('input[name="chosen_time"]');
  document.querySelectorAll('.rf-tod-b').forEach(function(b){
    b.addEventListener('click', function(){
      if (timeInput) timeInput.value = b.getAttribute('data-time');
      document.querySelectorAll('.rf-tod-b').forEach(function(x){ x.removeAttribute('data-on'); });
      b.setAttribute('data-on', '1');
    });
  });
  var p = document.querySelector('.rf-place');
  if (p) {
    document.querySelectorAll('input[name="meal_choice"]').forEach(function(r){
      r.addEventListener('change', function(){
        if (r.dataset.place) {
          var c = r.dataset.cuisine ? r.dataset.cuisine + ' · ' : '';
          p.textContent = c + r.dataset.label + ' at ' + r.dataset.place; p.style.display = 'block';
        } else { p.style.display = 'none'; }
      });
    });
  }

  var token = <?= json_encode((string) ($token ?? ''), JSON_UNESCAPED_SLASHES) ?>;
  var mm = document.getElementById('rfMapModal');
  var mf = document.getElementById('rfMapFrame');
  function openRfMap(place){
    if (!mm || !mf || !place) return;
    mf.innerHTML = '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=' + encodeURIComponent(place) + '&output=embed"></iframe>';
    mm.classList.add('show'); mm.setAttribute('aria-hidden','false');
  }
  function closeRfMap(){ if (mm){ mm.classList.remove('show'); mm.setAttribute('aria-hidden','true'); if (mf) mf.innerHTML=''; } }
  var rx = mm && mm.querySelector('.rf-map-x'); if (rx) rx.addEventListener('click', closeRfMap);
  if (mm) mm.addEventListener('click', function(e){ if (e.target === mm) closeRfMap(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeRfMap(); });
  document.querySelectorAll('input[name="chosen_place"], input[name="meal_choice"]').forEach(function(r){
    r.addEventListener('change', function(){ if (r.dataset.place) openRfMap(r.dataset.place); });
  });

  var pickup = document.querySelector('input[name="pickup_raw"]');
  if (pickup) pickup.addEventListener('change', function(){
    var v = (pickup.value || '').trim();
    var line = document.getElementById('rfPickupPrev');
    if (!line) { line = document.createElement('div'); line.id = 'rfPickupPrev'; line.style.cssText='font-size:12px;opacity:.8;margin-top:4px;'; pickup.parentNode.appendChild(line); }
    if (!v) { line.textContent=''; return; }
    line.textContent = 'Looking up…';
    fetch('/i/' + encodeURIComponent(token) + '/maps-preview?url=' + encodeURIComponent(v), { headers:{'Accept':'application/json'} })
      .then(function(r){ return r.json(); })
      .then(function(d){ line.textContent = d && (d.address || d.name) ? ('Pickup: ' + (d.address || d.name)) : ''; })
      .catch(function(){ line.textContent=''; });
  });
})();
</script>

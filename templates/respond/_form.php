<?php $error = $error ?? null; $meals = $meals ?? []; $places = $places ?? []; ?>
<?php if ($error): ?><p class="rf-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/i/<?= $e($token) ?>" class="rf-form">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <label class="rf-field">Pick a day &amp; time
    <input type="datetime-local" name="chosen_start" required>
  </label>
  <fieldset class="rf-meals">
    <legend>What are you craving?</legend>
    <div class="rf-chips">
      <?php foreach ($meals as $m): $p = $places[$m['key']] ?? null; $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : ''; ?>
        <label class="rf-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>" data-place="<?= $e($plabel) ?>" data-label="<?= $e($m['label']) ?>">
          <svg class="rf-ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <p class="rf-place" style="display:none"></p>
  <label class="rf-field">Any wish? (optional)
    <input type="text" name="meal_wish" placeholder="surprise me">
  </label>
  <label class="rf-field">Your contact (optional)
    <input type="text" name="crush_contact" placeholder="phone or @handle">
  </label>
  <label class="rf-field">Where should they pick you up? (optional)
    <input type="text" name="pickup_raw" placeholder="address or Google Maps link">
  </label>
  <button type="submit" class="rf-cta">Send my answer</button>
</form>
<script>
(function(){
  var p = document.querySelector('.rf-place');
  if (!p) return;
  document.querySelectorAll('input[name="meal_choice"]').forEach(function(r){
    r.addEventListener('change', function(){
      if (r.dataset.place) { p.textContent = r.dataset.label + ' at ' + r.dataset.place; p.style.display = 'block'; }
      else { p.style.display = 'none'; }
    });
  });
})();
</script>

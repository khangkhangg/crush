<?php $error = $error ?? null; $meals = $meals ?? []; $places = $places ?? []; ?>
<?php $collapseMeal = $collapseMeal ?? null; $focusVibe = $focusVibe ?? null; $focusOptions = $focusOptions ?? []; ?>
<?php if ($error): ?><p class="rf-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/i/<?= $e($token) ?>" class="rf-form">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <label class="rf-field">Pick a day &amp; time
    <input type="datetime-local" name="chosen_start" required>
  </label>
<?php if ($focusVibe !== null): ?>
  <input type="hidden" name="meal_choice" value="<?= $e($focusVibe['key']) ?>">
  <fieldset class="rf-meals">
    <legend><?= $e($focusVibe['label']) ?> — pick a spot</legend>
    <div class="rf-chips">
      <?php foreach ($focusOptions as $opt):
        $oname = $opt['place_resolved_name'] ?: $opt['place_name'];
        $ocuisine = $opt['cuisine'] ?? ''; $omap = $opt['place_clean_url'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="chosen_place" value="<?= $e($opt['id']) ?>">
          <span><?= $e($oname) ?><?php if ($ocuisine !== ''): ?> · <?= $e($ocuisine) ?><?php endif; ?></span>
          <?php if (is_string($omap) && str_starts_with((string) $omap, 'http')): ?>
            <a href="<?= $e($omap) ?>" target="_blank" rel="noopener" style="font-size:11px;">map</a>
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
    <span><?= $e($collapseMeal['label']) ?><?php if ($cspot !== ''): ?> at <?= $e($cspot) ?><?php endif; ?></span>
  </div>
<?php else: ?>
  <fieldset class="rf-meals">
    <legend>What are you craving?</legend>
    <div class="rf-chips">
      <?php foreach ($meals as $m): $p = $places[$m['key']] ?? null;
            $plabel = $p ? ($p['place_resolved_name'] ?: $p['place_name']) : '';
            $pcuisine = $p['cuisine'] ?? ''; ?>
        <label class="rf-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>"
                 data-place="<?= $e($plabel) ?>" data-cuisine="<?= $e($pcuisine) ?>" data-label="<?= $e($m['label']) ?>">
          <svg class="rf-ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?><?php if ($pcuisine !== ''): ?> · <?= $e($pcuisine) ?><?php endif; ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php endif; ?>
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
      if (r.dataset.place) {
        var c = r.dataset.cuisine ? r.dataset.cuisine + ' · ' : '';
        p.textContent = c + r.dataset.label + ' at ' + r.dataset.place; p.style.display = 'block';
      } else { p.style.display = 'none'; }
    });
  });
})();
</script>

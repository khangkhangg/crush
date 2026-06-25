<?php $state = $state ?? 'waiting'; $invite = $invite ?? null; $response = $response ?? null; $chosenPlace = $chosenPlace ?? null; $user = $user ?? []; $avatars = $avatars ?? []; $csrf = $csrf ?? ''; $returnTo = $returnTo ?? ''; ?>
<?php $content = function () use ($e, $state, $invite, $response, $chosenPlace, $user, $avatars, $csrf, $returnTo) {
  $crush = $invite['crush_name'] ?? ($invite['crush_email'] ?? 'your crush');
  ob_start();
  if ($state === 'missing'): ?>
    <h1>Not found</h1><p class="subtitle">We couldn't find that invite.</p>
  <?php elseif ($state === 'waiting'): ?>
    <h1 style="text-wrap:balance;">Waiting on <?= $e($crush) ?></h1>
    <p style="opacity:.85;">No response yet. We'll let you know the moment they answer.</p>
  <?php elseif ($state === 'locked'): ?>
    <?php include __DIR__ . '/../partials/avatars.php'; ?>
    <h1 style="text-wrap:balance;"><?= $e($crush) ?> answered!</h1>
    <p style="opacity:.85;">Add a few cute details so they know it's really you — then you'll see what they picked.</p>
    <?php include __DIR__ . '/../profile/_form.php'; ?>
  <?php else: /* reveal */
    $anon = (int) ($invite['is_anonymous'] ?? 0) === 1;
    $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
    $mapHref = $response['pickup_clean_url'] ?? '';
    $steps = [['Sent', $invite['created_at'] ?? null, true],
              ['Answered', $response['created_at'] ?? null, true],
              ['Confirmed', null, in_array($invite['status'] ?? '', ['confirmed', 'closed'], true)]]; ?>
    <h1 style="text-wrap:balance;">It's a date with <?= $e($crush) ?></h1>
    <p style="opacity:.8;font-size:13px;margin-top:-4px;">
      <?= $anon ? 'You sent this anonymously.' : 'Sent as yourself.' ?>
      <?= $e($invite['crush_email'] ?? '') ?>
    </p>
    <ol class="iv-timeline" style="list-style:none;padding:0;margin:14px 0;display:flex;gap:6px;">
      <?php foreach ($steps as [$lbl, $ts, $done]): ?>
        <li style="flex:1;text-align:center;font-size:11px;<?= $done ? 'color:#16a34a;font-weight:700;' : 'opacity:.4;' ?>">
          <div style="height:6px;border-radius:3px;background:<?= $done ? '#16a34a' : '#e7d4ff' ?>;margin-bottom:4px;"></div>
          <?= $e($lbl) ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if ($chosenPlace !== null):
      $cpName = $chosenPlace['place_resolved_name'] ?: $chosenPlace['place_name'];
      $cpCuisine = $chosenPlace['cuisine'] ?? '';
      $cpMap = $chosenPlace['place_clean_url'] ?? ''; ?>
      <p style="font-size:15px;margin:6px 0;">They picked
        <strong><?= $e($cpName) ?></strong><?php if ($cpCuisine !== ''): ?> · <?= $e($cpCuisine) ?><?php endif; ?>
        <?php if (is_string($cpMap) && str_starts_with((string) $cpMap, 'http')): ?>
          — <a href="<?= $e($cpMap) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;">map</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <ul style="list-style:none;padding:0;line-height:1.8;">
      <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
      <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
      <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
      <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
      <?php if ($place !== ''): ?>
        <li><strong>Pickup:</strong>
          <?php if (is_string($mapHref) && str_starts_with((string) $mapHref, 'http')): ?>
            <a href="<?= $e($mapHref) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;"><?= $e($place) ?></a>
          <?php else: ?><?= $e($place) ?><?php endif; ?>
        </li>
      <?php endif; ?>
    </ul>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
      <a href="/invites/<?= $e($invite['public_token']) ?>/calendar" style="padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Download calendar invite</a>
      <button type="button" class="iv-reshare" data-token="<?= $e($invite['public_token']) ?>"
              style="padding:12px 18px;border-radius:14px;border:1px solid #e7d4ff;background:#fff;color:#5a2a52;font-weight:600;cursor:pointer;">Copy link</button>
    </div>
    <script>
    document.querySelectorAll('.iv-reshare').forEach(function(b){
      b.addEventListener('click', function(){
        var t = b.getAttribute('data-token');
        var url = location.origin + '/i/' + t;
        if (navigator.clipboard && t) navigator.clipboard.writeText(url).then(function(){
          var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function(){ b.textContent = o; }, 1500);
        });
      });
    });
    </script>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

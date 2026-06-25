<?php $state = $state ?? 'waiting'; $invite = $invite ?? null; $response = $response ?? null; $chosenPlace = $chosenPlace ?? null; $user = $user ?? []; $avatars = $avatars ?? []; $csrf = $csrf ?? ''; $returnTo = $returnTo ?? ''; ?>
<?php $content = function () use ($e, $t, $state, $invite, $response, $chosenPlace, $user, $avatars, $csrf, $returnTo) {
  $crush = $invite['crush_name'] ?? ($invite['crush_email'] ?? 'your crush');
  ob_start();
  ?>
  <style>
    .reveal-hero{border-radius:26px;padding:18px;background:linear-gradient(150deg,#fff7fb,#f4ecff);box-shadow:0 12px 28px rgba(90,42,82,.1);margin-bottom:16px}
    .reveal-list{list-style:none;padding:0;margin:14px 0;display:grid;gap:10px}.reveal-list li{border-radius:16px;background:#fff;padding:10px 12px;box-shadow:0 8px 20px rgba(90,42,82,.08)}
    .iv-timeline{list-style:none;padding:0;margin:14px 0;display:flex;gap:6px}.iv-timeline li{flex:1;text-align:center;font-size:11px}.iv-timeline div{height:7px;border-radius:999px;margin-bottom:5px}
    .reveal-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.reveal-actions .btn{box-shadow:none}
  </style>
  <?php
  if ($state === 'missing'): ?>
    <div class="reveal-hero" data-redesign-page="reveal"><img class="generated-art generated-art--small generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"><p class="eyebrow">Crush</p><h1 class="title"><?= $e($t('Not found')) ?></h1><p class="copy"><?= $e($t('We couldn\'t find that invite.')) ?></p></div>
  <?php elseif ($state === 'waiting'): ?>
    <div class="reveal-hero" data-redesign-page="reveal"><img class="generated-art generated-art--small generated-art--center" src="/assets/generated/invite-envelope.png" alt="" loading="lazy" decoding="async"><p class="eyebrow"><?= $e($t('Waiting')) ?></p><h1 class="title"><?= $e($t('Waiting on')) ?> <?= $e($crush) ?></h1><p class="copy"><?= $e($t('No response yet. We\'ll let you know the moment they answer.')) ?></p></div>
  <?php elseif ($state === 'locked'): ?>
    <?php include __DIR__ . '/../partials/avatars.php'; ?>
    <div class="reveal-hero" data-redesign-page="reveal"><img class="generated-art generated-art--small generated-art--center" src="/assets/generated/sent-heart.png" alt="" loading="lazy" decoding="async"><p class="eyebrow"><?= $e($t('Answered')) ?></p><h1 class="title"><?= $e($crush) ?> <?= $e($t('answered!')) ?></h1><p class="copy"><?= $e($t('Add a few cute details so they know it\'s really you — then you\'ll see what they picked.')) ?></p></div>
    <?php include __DIR__ . '/../profile/_form.php'; ?>
  <?php else: /* reveal */
    $anon = (int) ($invite['is_anonymous'] ?? 0) === 1;
    $place = trim((string) (($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? '')));
    $mapHref = $response['pickup_clean_url'] ?? '';
    $steps = [['Sent', $invite['created_at'] ?? null, true],
              ['Answered', $response['created_at'] ?? null, true],
              ['Confirmed', null, in_array($invite['status'] ?? '', ['confirmed', 'closed'], true)]]; ?>
    <div class="reveal-hero" data-redesign-page="reveal"><img class="generated-art generated-art--small generated-art--center" src="/assets/generated/sent-heart.png" alt="" loading="lazy" decoding="async"><p class="eyebrow"><?= $e($t('Answered')) ?></p><h1 class="title"><?= $e($t('It\'s a date with')) ?> <?= $e($crush) ?></h1>
    <p class="copy" style="font-size:13px;margin:0;">
      <?= $anon ? $e($t('You sent this anonymously.')) : $e($t('Sent as yourself.')) ?>
      <?= $e($invite['crush_email'] ?? '') ?>
    </p></div>
    <ol class="iv-timeline">
      <?php foreach ($steps as [$lbl, $ts, $done]): ?>
        <li style="<?= $done ? 'color:#16a34a;font-weight:700;' : 'opacity:.4;' ?>">
          <div style="background:<?= $done ? '#16a34a' : '#e7d4ff' ?>;"></div>
          <?= $e($t($lbl)) ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if ($chosenPlace !== null):
      $cpName = $chosenPlace['place_resolved_name'] ?: $chosenPlace['place_name'];
      $cpCuisine = $chosenPlace['cuisine'] ?? '';
      $cpMap = $chosenPlace['place_clean_url'] ?? ''; ?>
      <p style="font-size:15px;margin:6px 0;"><?= $e($t('They picked')) ?>
        <strong><?= $e($cpName) ?></strong><?php if ($cpCuisine !== ''): ?> · <?= $e($cpCuisine) ?><?php endif; ?>
        <?php if (is_string($cpMap) && str_starts_with((string) $cpMap, 'http')): ?>
          — <a href="<?= $e($cpMap) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;"><?= $e($t('map')) ?></a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <ul class="reveal-list">
      <li><strong><?= $e($t('When')) ?>:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
      <?php if (!empty($response['meal_choice'])): ?><li><strong><?= $e($t('Craving')) ?>:</strong> <?= $e($t($response['meal_choice'])) ?></li><?php endif; ?>
      <?php if (!empty($response['meal_wish'])): ?><li><strong><?= $e($t('Wish')) ?>:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
      <?php if (!empty($response['crush_contact'])): ?><li><strong><?= $e($t('Contact')) ?>:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
      <?php if ($place !== ''): ?>
        <li><strong><?= $e($t('Pickup')) ?>:</strong>
          <?php if (is_string($mapHref) && str_starts_with((string) $mapHref, 'http')): ?>
            <a href="<?= $e($mapHref) ?>" target="_blank" rel="noopener" style="color:#ff3d8b;"><?= $e($place) ?></a>
          <?php else: ?><?= $e($place) ?><?php endif; ?>
        </li>
      <?php endif; ?>
    </ul>
    <div class="reveal-actions">
      <a href="/invites/<?= $e($invite['public_token']) ?>/calendar" class="btn"><?= $e($t('Download calendar invite')) ?></a>
      <button type="button" class="iv-reshare btn btn--ghost" data-token="<?= $e($invite['public_token']) ?>"><?= $e($t('Copy link')) ?></button>
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

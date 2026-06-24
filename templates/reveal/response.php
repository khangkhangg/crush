<?php $state = $state ?? 'waiting'; $invite = $invite ?? null; $response = $response ?? null; ?>
<?php $content = function () use ($e, $state, $invite, $response) {
  $crush = $invite['crush_name'] ?? ($invite['crush_email'] ?? 'your crush');
  ob_start();
  if ($state === 'missing'): ?>
    <h1>Not found</h1><p class="subtitle">We couldn't find that invite.</p>
  <?php elseif ($state === 'waiting'): ?>
    <h1 style="text-wrap:balance;">Waiting on <?= $e($crush) ?></h1>
    <p style="opacity:.85;">No response yet. We'll let you know the moment they answer.</p>
  <?php elseif ($state === 'locked'): ?>
    <h1 style="text-wrap:balance;"><?= $e($crush) ?> answered!</h1>
    <p style="opacity:.85;">Complete your profile to unlock what they picked and add it to your calendar.</p>
    <a href="/profile" style="display:inline-block;margin-top:10px;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Complete my profile</a>
  <?php else: /* reveal */ ?>
    <h1 style="text-wrap:balance;">It's a date with <?= $e($crush) ?></h1>
    <ul style="list-style:none;padding:0;line-height:1.8;">
      <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
      <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
      <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
      <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
      <?php $place = trim((string)(($response['pickup_name'] ?? '') . ' ' . ($response['pickup_address'] ?? ''))); ?>
      <?php if ($place !== ''): ?><li><strong>Pickup:</strong> <?= $e($place) ?></li><?php endif; ?>
    </ul>
    <a href="/invites/<?= $e($invite['public_token']) ?>/calendar" style="display:inline-block;margin-top:8px;padding:12px 18px;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;text-decoration:none;">Download calendar invite</a>
  <?php endif;
  return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

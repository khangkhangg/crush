<?php $response = $response ?? []; $mapHref = $mapHref ?? null; $location = $location ?? ''; ?>
<div style="font-family:Segoe UI,system-ui,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border-radius:16px;">
  <h1 style="color:#ff3d8b;font-size:22px;"><?= $e($crushName) ?> said yes</h1>
  <p style="color:#444;">Here's what they picked. The calendar invite is attached &mdash; add it to your phone and set a reminder.</p>
  <ul style="color:#444;line-height:1.7;list-style:none;padding:0;">
    <li><strong>When:</strong> <?= $e($response['chosen_start'] ?? '') ?></li>
    <?php if (!empty($response['meal_choice'])): ?><li><strong>Craving:</strong> <?= $e($response['meal_choice']) ?></li><?php endif; ?>
    <?php if (!empty($response['meal_wish'])): ?><li><strong>Wish:</strong> <?= $e($response['meal_wish']) ?></li><?php endif; ?>
    <?php if (!empty($response['crush_contact'])): ?><li><strong>Contact:</strong> <?= $e($response['crush_contact']) ?></li><?php endif; ?>
    <?php if ($location !== ''): ?>
      <li><strong>Pickup:</strong>
        <?php if ($mapHref): ?><a href="<?= $e($mapHref) ?>" style="color:#ff3d8b;"><?= $e($location) ?></a>
        <?php else: ?><?= $e($location) ?><?php endif; ?>
      </li>
    <?php endif; ?>
  </ul>
</div>

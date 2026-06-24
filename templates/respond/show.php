<?php
$message = $message ?? null; $options = $options ?? []; $meals = $meals ?? [];
$error = $error ?? null;
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Crush') ?></title>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/themes/<?= $e($theme) ?>.css"></head>
<body class="theme-<?= $e($theme) ?>">
<?php include __DIR__ . '/../partials/icons.php'; ?>
<main class="card invite-card">
  <?php if ($error): ?><p class="error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <p class="kicker"><?= $e($senderLabel) ?> has a crush on you</p>
  <?php if ($message): ?><p class="message"><?= $e($message) ?></p><?php endif; ?>
  <form method="post" action="/i/<?= $e($token) ?>" class="respond-form">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label class="field">Pick a day &amp; time
      <input type="datetime-local" name="chosen_start" required>
    </label>
    <fieldset class="meals">
      <legend>What are you craving?</legend>
      <?php foreach ($meals as $m): ?>
        <label class="meal-chip">
          <input type="radio" name="meal_choice" value="<?= $e($m['key']) ?>">
          <svg class="ic"><use href="#<?= $e($m['icon']) ?>"/></svg>
          <span><?= $e($m['label']) ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <label class="field">Any wish? (optional)
      <input type="text" name="meal_wish" placeholder="surprise me">
    </label>
    <label class="field">Your contact (optional)
      <input type="text" name="crush_contact" placeholder="phone or @handle">
    </label>
    <label class="field">Where should they pick you up? (optional)
      <input type="text" name="pickup_raw" placeholder="address or Google Maps link">
    </label>
    <button type="submit" class="cta">Send my answer</button>
  </form>
</main>
</body></html>

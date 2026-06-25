<?php $content = function () use ($e, $t) { ob_start(); ?>
  <h1 style="text-wrap:balance;"><?= $e($t('Real life, but make it a date')) ?></h1>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('Crush is a tiny app for a big feeling: telling someone you like them. No followers, no swiping — just a sweet little invite to spend real time together.')) ?></p>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('We built it so asking someone out feels easy, not scary. Send it as a secret admirer, or as you. They pick the day, the vibe, the spot. You just show up.')) ?></p>
  <p style="opacity:.9;line-height:1.6;"><?= $e($t('Whether it is a first hello or reconnecting with someone you miss, this is your nudge to put down the phone and actually hang out. Connect like a human again.')) ?></p>
  <p style="margin-top:18px;"><a href="/" style="color:#ff3d8b;font-weight:700;text-decoration:none;"><?= $e($t('Send a crush invite')) ?></a></p>
<?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/layout.php';

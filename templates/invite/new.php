<?php $error = $error ?? null; $old = $old ?? []; $csrf = $csrf ?? ''; ?>
<?php $content = function () use ($e, $csrf, $error, $old) {
  $val = fn(string $k) => $e($old[$k] ?? '');
  ob_start(); ?>
  <h1 style="text-wrap:balance;">Send a crush invite</h1>
  <?php if ($error): ?><p role="alert" style="color:#b3243b;"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" action="/invites" style="display:flex;flex-direction:column;gap:12px;">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label>Their email
      <input type="email" name="crush_email" required value="<?= $val('crush_email') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>Their name (optional)
      <input type="text" name="crush_name" value="<?= $val('crush_name') ?>"
             style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
    </label>
    <label>A little message (optional)
      <textarea name="message" rows="3"
                style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;"><?= $val('message') ?></textarea>
    </label>
    <label>When should they pick?
      <select name="date_mode" style="width:100%;padding:11px;border-radius:12px;border:1px solid #e7d4ff;">
        <option value="instant">Let them pick any time (final)</option>
        <option value="confirm">They propose, I confirm</option>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="is_anonymous" value="1"> Send anonymously (a secret admirer)
    </label>
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" name="reveal_on_response" value="1"> Reveal me after they respond
    </label>
    <button type="submit"
            style="padding:12px;border:0;border-radius:14px;background:#ff3d8b;color:#fff;font-weight:700;cursor:pointer;">
      Create my invite
    </button>
  </form>
  <?php return ob_get_clean(); };
$body = $content();
include __DIR__ . '/../layout.php';

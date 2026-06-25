<?php
declare(strict_types=1);

/**
 * Generates migrations/0024_seed_branded_email_templates.sql:
 * branded HTML email templates for welcome/invite/result/magic/confirm
 * across all 10 languages. Copy per language is read from
 * /tmp/crush_email_i18n/<lang>.json (en is inline below as source-of-truth).
 *
 * Run: php bin/gen_email_templates.php > migrations/0024_seed_branded_email_templates.sql
 */

const LANGS = ['en','vi','es','zh','hi','pt','fr','ko','ja','th'];
const ASSETS = 'https://crush.didudi.com/assets/generated';
const PINK = '#ff3d8b';

/** English source-of-truth copy. Other langs override these keys via JSON. */
$EN = [
    'footer'           => 'Sent with care by Crush',
    'welcome_subject'  => 'Welcome to Crush',
    'welcome_h'        => 'You\'re in!',
    'welcome_p'        => 'Ready to ask your crush out? Make your first invite — stay anonymous, keep it cute.',
    'welcome_btn'      => 'Open Crush',
    'magic_subject'    => 'Your Crush sign-in link',
    'magic_h'          => 'Tap to sign in',
    'magic_p'          => 'Here is your magic link. It expires soon, so open it on this device.',
    'magic_btn'        => 'Sign in',
    'invite_subject'   => 'You have a crush invite',
    'invite_h'         => 'Someone has a crush on you',
    'invite_sub'       => 'A little date quest, just for you.',
    'invite_p'         => 'Tap below to pick a day, a meal vibe, and where you would like to meet. No account needed.',
    'invite_btn'       => 'Open my invite',
    'invite_unsub'     => 'Not interested? Block and report',
    'result_subject'   => '{{crushName}} answered your invite',
    'result_h'         => '{{crushName}} said yes',
    'result_p'         => 'Here is what they picked. Your calendar invite is attached.',
    'confirm_subject'  => 'You are all set',
    'confirm_h'        => 'Your answer is on its way',
    'confirm_p'        => 'Nice! We sent your pick over. Here is what you chose:',
    'confirm_close'    => 'We will let you know when it is locked in.',
    'label_when'       => 'When',
    'label_craving'    => 'Craving',
    'label_pickup'     => 'Pickup',
    'label_map'        => 'Map',
];

/** Load a language's copy: en inline, others from JSON, falling back to en. */
function copyFor(string $lang, array $en): array {
    if ($lang === 'en') return $en;
    $file = "/tmp/crush_email_i18n/{$lang}.json";
    $over = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    return array_merge($en, $over);
}

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** The branded email shell. $rows = list of [label, '{{var}}'] detail rows (optional). */
function shell(string $hero, string $eyebrow, string $headline, array $paragraphs, ?string $btnText, ?string $btnVar, array $rows, ?string $footnote, string $footer): string {
    $pink = PINK;
    $h  = '<div style="background:#f4ecff;padding:24px 12px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;">';
    $h .= '<div style="max-width:460px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;">';
    // hero
    $h .= '<div style="background:' . $pink . ';padding:26px 24px;text-align:center;">';
    $h .= '<img src="' . ASSETS . '/' . $hero . '" width="76" height="76" alt="" style="display:inline-block;border:0;outline:none;">';
    $h .= '<div style="color:#ffe3f1;font-size:12px;letter-spacing:2px;text-transform:uppercase;margin-top:8px;">' . esc($eyebrow) . '</div>';
    $h .= '</div>';
    // body
    $h .= '<div style="padding:26px 26px 22px;">';
    $h .= '<h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;color:#5a2a52;font-weight:700;">' . $headline . '</h1>';
    foreach ($paragraphs as $p) {
        $h .= '<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#5f5e5a;">' . $p . '</p>';
    }
    if ($rows) {
        $h .= '<table role="presentation" width="100%" style="border-collapse:collapse;margin:0 0 18px;background:#fbeaf0;border-radius:14px;">';
        foreach ($rows as [$label, $var]) {
            $h .= '<tr><td style="padding:9px 16px;font-size:13px;color:#993556;font-weight:700;width:34%;">' . esc($label) . '</td>';
            $h .= '<td style="padding:9px 16px;font-size:13px;color:#72243e;">' . $var . '</td></tr>';
        }
        $h .= '</table>';
    }
    if ($btnText !== null && $btnVar !== null) {
        $h .= '<a href="' . $btnVar . '" style="display:block;text-align:center;padding:14px 20px;background:' . $pink . ';color:#ffffff;border-radius:14px;text-decoration:none;font-weight:700;font-size:15px;">' . esc($btnText) . '</a>';
    }
    if ($footnote !== null) {
        $h .= '<p style="margin:18px 0 0;font-size:11px;line-height:1.5;color:#c3a7b8;text-align:center;">' . $footnote . '</p>';
    }
    $h .= '</div>';
    // footer
    $h .= '<div style="padding:14px 24px 20px;text-align:center;font-size:11px;color:#c3a7b8;">' . esc($footer) . '</div>';
    $h .= '</div></div>';
    return $h;
}

/** Build [subject, body_html] for one template key in one language. */
function build(string $key, array $c): array {
    switch ($key) {
        case 'welcome':
            return [$c['welcome_subject'], shell('crush-mascot.png', 'Crush', esc($c['welcome_h']),
                [esc($c['welcome_p'])], $c['welcome_btn'], '{{link}}', [], null, $c['footer'])];
        case 'magic':
            return [$c['magic_subject'], shell('crush-mascot.png', 'Crush', esc($c['magic_h']),
                [esc($c['magic_p'])], $c['magic_btn'], '{{link}}', [], null, $c['footer'])];
        case 'invite':
            return [$c['invite_subject'], shell('invite-envelope.png', 'Crush',
                esc($c['invite_h']),
                ['<em style="color:#a06a8e;">&ldquo;{{message}}&rdquo;</em>', esc($c['invite_sub']) . ' ' . esc($c['invite_p'])],
                $c['invite_btn'], '{{link}}', [],
                esc($c['invite_unsub']) . ': {{unsubscribe}}', $c['footer'])];
        case 'result':
            return [$c['result_subject'], shell('sent-heart.png', 'Crush', esc($c['result_h']),
                [esc($c['result_p'])], null, null,
                [[$c['label_when'], '{{when}}'], [$c['label_craving'], '{{meal}}'], [$c['label_pickup'], '{{place}}'], [$c['label_map'], '{{mapHref}}']],
                null, $c['footer'])];
        case 'confirm':
            return [$c['confirm_subject'], shell('sent-heart.png', 'Crush', esc($c['confirm_h']),
                [esc($c['confirm_p'])], null, null,
                [[$c['label_when'], '{{when}}'], [$c['label_craving'], '{{meal}}'], [$c['label_pickup'], '{{place}}']],
                esc($c['confirm_close']), $c['footer'])];
    }
    throw new RuntimeException("unknown key $key");
}

$keys = ['welcome','magic','invite','result','confirm'];
$rows = [];
foreach (LANGS as $lang) {
    $c = copyFor($lang, $EN);
    foreach ($keys as $key) {
        [$subject, $body] = build($key, $c);
        $rows[] = sprintf("('%s','%s',%s,%s)", $key, $lang,
            "'" . str_replace("'", "''", $subject) . "'",
            "'" . str_replace("'", "''", $body) . "'");
    }
}

echo "-- Branded HTML email templates (welcome/magic/invite/result/confirm) x 10 languages.\n";
echo "-- Generated by bin/gen_email_templates.php. Uses hosted art at " . ASSETS . ".\n";
echo "INSERT INTO email_templates (`key`, lang, subject, body_html) VALUES\n";
echo implode(",\n", $rows) . "\n";
echo "ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html);\n";

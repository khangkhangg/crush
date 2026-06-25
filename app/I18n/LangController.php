<?php
declare(strict_types=1);

namespace App\I18n;

use App\Core\Locale;
use App\Core\Response;

final class LangController
{
    public function set(string $code, ?string $referer): Response
    {
        $dest = (is_string($referer) && str_starts_with($referer, '/') && !str_starts_with($referer, '//')) ? $referer : '/';
        if (!Locale::isSupported($code)) {
            return (new Response('', 302))->withHeader('Location', '/');
        }
        $cookie = 'lang=' . $code . '; Path=/; Max-Age=31536000; SameSite=Lax';
        return (new Response('', 302))->withHeader('Location', $dest)->withHeader('Set-Cookie', $cookie);
    }
}

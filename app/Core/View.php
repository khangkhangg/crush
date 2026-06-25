<?php
declare(strict_types=1);

namespace App\Core;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

final class View
{
    public function __construct(
        private string $templateDir,
        private ?\App\I18n\Translator $translator = null,
    ) {}

    public function render(string $name, array $data = []): string
    {
        $path = $this->templateDir . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$name}");
        }
        $translator = $this->translator;
        $render = static function (string $__path, array $__data) use ($translator): string {
            // Local alias so templates can call e() unqualified.
            $e = static fn(mixed $v): string => \App\Core\e($v);
            $t = static fn(string $s): string => $translator !== null ? $translator->t($s) : $s;
            $lang = $translator !== null ? $translator->lang() : 'en';
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        };
        return $render($path, $data);
    }
}

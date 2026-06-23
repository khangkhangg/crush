<?php
declare(strict_types=1);

namespace App\Core;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

final class View
{
    public function __construct(private string $templateDir) {}

    public function render(string $name, array $data = []): string
    {
        $path = $this->templateDir . '/' . $name . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$name}");
        }
        $render = static function (string $__path, array $__data): string {
            // Local alias so templates can call e() unqualified.
            $e = static fn(mixed $v): string => \App\Core\e($v);
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        };
        return $render($path, $data);
    }
}

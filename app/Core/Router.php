<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,regex:string,names:string[],handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$names) {
            $names[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'names'   => $names,
            'handler' => $handler,
        ];
    }

    /** @return array{handler:callable,params:array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                return [
                    'handler' => $route['handler'],
                    'params'  => array_combine($route['names'], $matches) ?: [],
                ];
            }
        }
        return null;
    }
}

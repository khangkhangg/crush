<?php
declare(strict_types=1);

namespace App\Maps;

use App\Core\Response;

final class MapsController
{
    public function __construct(private LinkResolver $maps) {}

    public function preview(?int $userId, string $url): Response
    {
        if ($userId === null) {
            return $this->json(['error' => 'auth'], 401);
        }
        $url = trim($url);
        if ($url === '') {
            return $this->json(['name' => null, 'address' => null]);
        }
        $r = $this->maps->resolve($url);
        return $this->json(['name' => $r['name'], 'address' => $r['address']]);
    }

    private function json(array $data, int $status = 200): Response
    {
        return (new Response((string) json_encode($data), $status))
            ->withHeader('Content-Type', 'application/json');
    }
}

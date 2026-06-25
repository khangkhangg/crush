<?php
declare(strict_types=1);

namespace App\Profile;

use App\Core\Response;

final class AvatarController
{
    public function __construct(private AvatarStore $store) {}

    public function show(int $userId): Response
    {
        if (!$this->store->has($userId)) {
            return new Response('', 404);
        }
        $bytes = (string) file_get_contents($this->store->path($userId));
        return (new Response($bytes, 200))
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Cache-Control', 'private, max-age=300');
    }
}

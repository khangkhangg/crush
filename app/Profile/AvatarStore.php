<?php
declare(strict_types=1);

namespace App\Profile;

final class AvatarStore
{
    private const MAX_BYTES = 5_242_880;
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    public function __construct(private string $dir) {}

    public function path(int $userId): string
    {
        return rtrim($this->dir, '/') . '/' . $userId . '.png';
    }

    public function has(int $userId): bool
    {
        return is_file($this->path($userId));
    }

    public function store(int $userId, string $tmpPath): bool
    {
        if (!is_file($tmpPath) || filesize($tmpPath) > self::MAX_BYTES) {
            return false;
        }
        $info = @getimagesize($tmpPath);
        if ($info === false || !in_array($info[2], self::ALLOWED, true)) {
            return false;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmpPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmpPath),
            IMAGETYPE_GIF  => @imagecreatefromgif($tmpPath),
            default        => false,
        };
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $x = (int) (($w - $side) / 2);
        $y = (int) (($h - $side) / 2);
        $dst = imagecreatetruecolor(256, 256);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, 256, 256, $side, $side);
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $ok = imagepng($dst, $this->path($userId));
        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $ok;
    }
}

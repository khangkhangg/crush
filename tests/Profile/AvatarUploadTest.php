<?php
declare(strict_types=1);

namespace Tests\Profile;

use App\Profile\AvatarController;
use App\Profile\AvatarStore;
use PHPUnit\Framework\TestCase;

final class AvatarUploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/crush_av_' . bin2hex(random_bytes(4));
        @mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    private function makePng(string $path, int $w = 400, int $h = 300): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 80, 140));
        imagepng($im, $path);
        imagedestroy($im);
    }

    public function test_store_crops_to_256_square_png(): void
    {
        $store = new AvatarStore($this->dir);
        $src = $this->dir . '/src.png';
        $this->makePng($src, 400, 300);
        $this->assertTrue($store->store(7, $src));
        $this->assertTrue($store->has(7));
        [$w, $h, $type] = getimagesize($store->path(7));
        $this->assertSame(256, $w);
        $this->assertSame(256, $h);
        $this->assertSame(IMAGETYPE_PNG, $type);
    }

    public function test_store_rejects_non_image(): void
    {
        $store = new AvatarStore($this->dir);
        $bad = $this->dir . '/bad.txt';
        file_put_contents($bad, 'not an image');
        $this->assertFalse($store->store(8, $bad));
        $this->assertFalse($store->has(8));
    }

    public function test_controller_serves_png_then_404(): void
    {
        $store = new AvatarStore($this->dir);
        $ctrl = new AvatarController($store);
        $this->assertSame(404, $ctrl->show(9)->status());
        $src = $this->dir . '/s.png';
        $this->makePng($src);
        $store->store(9, $src);
        $res = $ctrl->show(9);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('image/png', implode(' ', $res->headers()));
    }
}

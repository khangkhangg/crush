<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function test_renders_template_with_escaped_data(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $html = $view->render('_test_fixture', ['name' => '<script>x</script>']);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function test_missing_template_throws(): void
    {
        $view = new View(\dirname(__DIR__, 2) . '/templates');
        $this->expectException(\RuntimeException::class);
        $view->render('does_not_exist');
    }
}

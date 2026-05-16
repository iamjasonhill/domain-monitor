<?php

namespace Tests\Unit;

use App\Services\CommandFleetTechnicalSeoBrowserRenderer;
use PHPUnit\Framework\TestCase;

class CommandFleetTechnicalSeoBrowserRendererTest extends TestCase
{
    public function test_renderer_runs_configured_command_with_url_environment(): void
    {
        $command = PHP_BINARY.' -r \'echo json_encode(["title" => "Rendered", "url" => getenv("FLEET_SEO_RENDER_URL"), "viewport" => ["width" => 390]]);\'';
        $renderer = new CommandFleetTechnicalSeoBrowserRenderer($command);

        $result = $renderer->render('https://example.com/');

        $this->assertTrue($result['available']);
        $this->assertSame('https://example.com/', $result['url']);
        $this->assertSame('Rendered', $result['title']);
        $this->assertSame(390, $result['viewport']['width']);
    }

    public function test_renderer_returns_unavailable_when_command_returns_invalid_json(): void
    {
        $renderer = new CommandFleetTechnicalSeoBrowserRenderer(PHP_BINARY.' -r \'echo "nope";\'');

        $result = $renderer->render('https://example.com/');

        $this->assertFalse($result['available']);
        $this->assertSame('https://example.com/', $result['url']);
        $this->assertSame('Browser-render command did not return a JSON object.', $result['reason']);
    }
}

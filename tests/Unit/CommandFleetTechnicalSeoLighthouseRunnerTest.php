<?php

namespace Tests\Unit;

use App\Services\CommandFleetTechnicalSeoLighthouseRunner;
use PHPUnit\Framework\TestCase;

class CommandFleetTechnicalSeoLighthouseRunnerTest extends TestCase
{
    public function test_runner_runs_configured_command_with_url_environment(): void
    {
        $command = PHP_BINARY.' -r \'echo json_encode(["metrics" => ["lcp_ms" => 1200], "url" => getenv("FLEET_SEO_LIGHTHOUSE_URL")]);\'';
        $runner = new CommandFleetTechnicalSeoLighthouseRunner($command);

        $result = $runner->run('https://example.com/');

        $this->assertTrue($result['available']);
        $this->assertSame('https://example.com/', $result['url']);
        $this->assertSame(1200, $result['metrics']['lcp_ms']);
    }

    public function test_runner_returns_unavailable_when_command_returns_invalid_json(): void
    {
        $runner = new CommandFleetTechnicalSeoLighthouseRunner(PHP_BINARY.' -r \'echo "nope";\'');

        $result = $runner->run('https://example.com/');

        $this->assertFalse($result['available']);
        $this->assertSame('https://example.com/', $result['url']);
        $this->assertSame('Lighthouse lab command did not return a JSON object.', $result['reason']);
    }
}

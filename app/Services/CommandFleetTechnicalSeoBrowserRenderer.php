<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class CommandFleetTechnicalSeoBrowserRenderer implements FleetTechnicalSeoBrowserRenderer
{
    public function __construct(
        private readonly string $command,
        private readonly int $timeoutSeconds = 20,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function render(string $url): array
    {
        if (trim($this->command) === '') {
            return $this->unavailable($url, 'Browser-render command is empty.');
        }

        $process = Process::fromShellCommandline(
            command: $this->command,
            env: ['FLEET_SEO_RENDER_URL' => $url],
            timeout: max(1, $this->timeoutSeconds),
        );

        try {
            $process->run();
        } catch (\Throwable $exception) {
            return $this->unavailable($url, $exception->getMessage());
        }

        if (! $process->isSuccessful()) {
            return $this->unavailable($url, trim($process->getErrorOutput()) ?: 'Browser-render command failed.');
        }

        $payload = json_decode(trim($process->getOutput()), true);
        if (! is_array($payload)) {
            return $this->unavailable($url, 'Browser-render command did not return a JSON object.');
        }

        $payload['available'] = (bool) ($payload['available'] ?? true);
        $payload['url'] = is_string($payload['url'] ?? null) ? $payload['url'] : $url;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $url, string $reason): array
    {
        return [
            'available' => false,
            'url' => $url,
            'reason' => $reason,
        ];
    }
}

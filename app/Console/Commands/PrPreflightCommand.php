<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class PrPreflightCommand extends Command
{
    private const PROCESS_TIMEOUT_SECONDS = 1800;

    protected $signature = 'pr:preflight
                            {--skip-config-clear : Skip php artisan config:clear}
                            {--skip-pint : Skip vendor/bin/pint --dirty}
                            {--skip-analyse : Skip phpstan analysis}
                            {--skip-tests : Skip php artisan test}';

    protected $description = 'Run the local PR preflight checks before pushing a branch';

    public function handle(): int
    {
        $steps = collect([
            $this->option('skip-config-clear') ? null : [
                'label' => 'Clear config cache',
                'command' => [PHP_BINARY, 'artisan', 'config:clear', '--ansi'],
            ],
            $this->option('skip-pint') ? null : [
                'label' => 'Format dirty PHP files with Pint',
                'command' => ['./vendor/bin/pint', '--dirty'],
            ],
            $this->option('skip-analyse') ? null : [
                'label' => 'Run PHPStan analysis',
                'command' => ['./vendor/bin/phpstan', 'analyse', '--memory-limit=2G'],
            ],
            $this->option('skip-tests') ? null : [
                'label' => 'Run PHPUnit suite',
                'command' => [PHP_BINARY, 'artisan', 'test'],
            ],
        ])->filter();

        if ($steps->isEmpty()) {
            $this->warn('No PR preflight steps were selected.');

            return self::SUCCESS;
        }

        foreach ($steps as $step) {
            /** @var array{label:string,command:array<int,string>} $step */
            $this->newLine();
            $this->info($step['label']);
            $this->line('  '.implode(' ', $step['command']));

            try {
                $result = $this->runCliCommand($step['command']);
            } catch (Throwable $exception) {
                $this->newLine();
                $this->error("PR preflight failed during: {$step['label']}");
                $this->warn($exception->getMessage());

                return self::FAILURE;
            }

            $this->writeProcessOutput($result);

            if ($result->failed()) {
                $this->newLine();
                $this->error("PR preflight failed during: {$step['label']}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('PR preflight passed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runCliCommand(array $command): ProcessResult
    {
        return Process::path(base_path())
            ->env($this->environmentForCommand($command))
            ->timeout(self::PROCESS_TIMEOUT_SECONDS)
            ->run($command);
    }

    /**
     * @param  array<int, string>  $command
     * @return array<string, false>
     */
    private function environmentForCommand(array $command): array
    {
        if (! $this->isArtisanCommand($command)) {
            return [];
        }

        $keys = collect([
            ...$this->environmentKeysFromFile(base_path('.env')),
            ...$this->environmentKeysFromFile(base_path('.env.testing')),
        ])
            ->unique()
            ->values()
            ->all();

        /** @var array<string, false> $environment */
        $environment = array_fill_keys($keys, false);

        return $environment;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function isArtisanCommand(array $command): bool
    {
        if (count($command) < 2) {
            return false;
        }

        return Str::startsWith(basename($command[0]), 'php')
            && $command[1] === 'artisan';
    }

    /**
     * @return list<string>
     */
    private function environmentKeysFromFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            if (preg_match('/^([A-Z0-9_]+)=/', $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }

    private function writeProcessOutput(ProcessResult $result): void
    {
        $output = trim($result->output());

        if ($output !== '') {
            $this->line($output);
        }

        $errorOutput = trim($result->errorOutput());

        if ($errorOutput !== '') {
            $this->warn($errorOutput);
        }
    }
}

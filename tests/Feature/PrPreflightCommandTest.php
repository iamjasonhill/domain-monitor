<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PrPreflightCommandTest extends TestCase
{
    public function test_it_runs_the_default_preflight_steps(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function () {
            return Process::result('ok');
        });

        $this->artisan('pr:preflight')
            ->expectsOutputToContain('Clear config cache')
            ->expectsOutputToContain('Format dirty PHP files with Pint')
            ->expectsOutputToContain('Run PHPStan analysis')
            ->expectsOutputToContain('Run PHPUnit suite')
            ->expectsOutputToContain('PR preflight passed.')
            ->assertSuccessful();

        Process::assertRanTimes(function (PendingProcess $process): bool {
            return $process->command === [PHP_BINARY, 'artisan', 'config:clear', '--ansi'];
        });

        Process::assertRanTimes(function (PendingProcess $process): bool {
            return $process->command === ['./vendor/bin/pint', '--dirty'];
        });

        Process::assertRanTimes(function (PendingProcess $process): bool {
            return $process->command === ['./vendor/bin/phpstan', 'analyse', '--memory-limit=2G'];
        });

        Process::assertRanTimes(function (PendingProcess $process): bool {
            return $process->command === [PHP_BINARY, 'artisan', 'test'];
        });
    }

    public function test_it_honors_skip_flags(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function () {
            return Process::result('ok');
        });

        $this->artisan('pr:preflight', [
            '--skip-config-clear' => true,
            '--skip-pint' => true,
            '--skip-analyse' => true,
        ])
            ->doesntExpectOutputToContain('Clear config cache')
            ->doesntExpectOutputToContain('Format dirty PHP files with Pint')
            ->doesntExpectOutputToContain('Run PHPStan analysis')
            ->expectsOutputToContain('Run PHPUnit suite')
            ->assertSuccessful();

        Process::assertRanTimes(function (PendingProcess $process): bool {
            return $process->command === [PHP_BINARY, 'artisan', 'test'];
        });

        Process::assertNotRan(function (PendingProcess $process): bool {
            return $process->command === [PHP_BINARY, 'artisan', 'config:clear', '--ansi'];
        });
    }

    public function test_it_stops_on_the_first_failed_step(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['./vendor/bin/phpstan', 'analyse', '--memory-limit=2G']) {
                return Process::result('', 'phpstan failed', 1);
            }

            return Process::result('ok');
        });

        $this->artisan('pr:preflight')
            ->expectsOutputToContain('PR preflight failed during: Run PHPStan analysis')
            ->assertFailed();

        Process::assertNotRan(function (PendingProcess $process): bool {
            return $process->command === [PHP_BINARY, 'artisan', 'test'];
        });
    }
}

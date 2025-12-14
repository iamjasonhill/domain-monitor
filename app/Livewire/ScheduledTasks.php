<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class ScheduledTasks extends Component
{
    public array $tasks = [];

    public function mount(): void
    {
        $this->loadScheduledTasks();
    }

    public function loadScheduledTasks(): void
    {
        // Get scheduled tasks by running schedule:list command
        Artisan::call('schedule:list');
        $output = Artisan::output();

        // Parse the output to extract task information
        $lines = explode("\n", trim($output));
        $this->tasks = [];

        foreach ($lines as $line) {
            // Skip header lines and empty lines
            if (empty(trim($line)) || str_contains($line, 'Next Due') || str_contains($line, 'Command') || str_contains($line, '---')) {
                continue;
            }

            // Parse the schedule:list output format
            // Format: "0  *   * * *  php artisan domains:health-check --all --type=http  Next Due: 16 minutes from now"
            if (preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+php artisan (.+?)(?:\s+Next Due:\s+(.+))?$/', $line, $matches)) {
                $expression = trim($matches[1]);
                $command = 'php artisan '.trim($matches[2]);
                $nextRun = isset($matches[3]) ? trim($matches[3]) : 'N/A';

                // Extract description from command
                $description = $this->getCommandDescription($command);
                $scheduleText = $this->parseCronExpression($expression, 'UTC');

                $this->tasks[] = [
                    'description' => $description,
                    'command' => $command,
                    'expression' => $expression,
                    'schedule' => $scheduleText,
                    'timezone' => 'UTC',
                    'next_run' => 'N/A',
                    'next_run_relative' => $nextRun,
                ];
            }
        }

        // Sort by next run relative time
        usort($this->tasks, function ($a, $b) {
            return strcmp($a['next_run_relative'], $b['next_run_relative']);
        });
    }

    /**
     * Get human-readable description for command
     */
    private function getCommandDescription(string $command): string
    {
        $descriptions = [
            'domains:detect-platforms' => 'Platform Detection',
            'domains:detect-hosting' => 'Hosting Detection',
            'domains:health-check --all --type=http' => 'HTTP Health Checks',
            'domains:health-check --all --type=ssl' => 'SSL Certificate Checks',
            'domains:health-check --all --type=dns' => 'DNS Health Checks',
            'domains:sync-synergy-expiry' => 'Synergy Wholesale Expiry Sync',
            'domains:sync-dns-records' => 'DNS Records Sync',
        ];

        foreach ($descriptions as $cmd => $desc) {
            if (str_contains($command, $cmd)) {
                return $desc;
            }
        }

        return 'Scheduled Task';
    }

    /**
     * Parse cron expression to human-readable text
     */
    private function parseCronExpression(string $expression, string $timezone): string
    {
        $parts = explode(' ', $expression);

        if (count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Common patterns
        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every minute';
        }

        if ($minute !== '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return "Every hour at minute {$minute}";
        }

        if ($minute !== '*' && $hour !== '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return "Daily at {$hour}:{$minute}";
        }

        if ($minute === '0' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every hour';
        }

        if ($minute === '0' && $hour !== '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return "Daily at {$hour}:00";
        }

        if ($minute === '0' && $hour === '*/6' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every 6 hours';
        }

        if ($minute === '0' && $hour === '0' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Daily at midnight';
        }

        if ($minute === '0' && $hour === '0' && $day === '*' && $month === '*' && $weekday === '0') {
            return 'Weekly on Sunday at midnight';
        }

        // Return the expression if we can't parse it
        return $expression;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.scheduled-tasks');
    }
}

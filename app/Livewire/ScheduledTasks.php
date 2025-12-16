<?php

namespace App\Livewire;

use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ScheduledTasks extends Component
{
    /**
     * @var array<int, array{description: string, command: string, expression: string, schedule: string, timezone: string, next_run: string, next_run_relative: string}>
     */
    public array $tasks = [];

    public function mount(): void
    {
        $this->loadScheduledTasks();
    }

    public function loadScheduledTasks(): void
    {
        // Load console routes to ensure scheduled tasks are registered
        require base_path('routes/console.php');

        // Get scheduled tasks from Laravel's scheduler
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();

        $this->tasks = collect($events)->map(function (Event $event): array {
            $description = $event->description ?: 'No description';
            $command = $event->command ?: 'N/A';
            $expression = $event->expression;
            $timezone = $event->timezone instanceof DateTimeZone
                ? $event->timezone->getName()
                : (string) $event->timezone;

            // If no description, try to extract from command
            if ($description === 'No description' || empty($description)) {
                $description = $this->getCommandDescription($command);
            }

            // Parse the cron expression to get a human-readable schedule
            $scheduleText = $this->parseCronExpression($expression, $timezone);

            // Get next run time
            try {
                $nextRun = $event->nextRunDate();
                $nextRunFormatted = $nextRun->format('Y-m-d H:i:s T');
                $nextRunRelative = $nextRun->diffForHumans();
            } catch (\Exception $e) {
                $nextRunFormatted = 'N/A';
                $nextRunRelative = 'N/A';
            }

            return [
                'description' => $description,
                'command' => $command,
                'expression' => $expression,
                'schedule' => $scheduleText,
                'timezone' => $timezone,
                'next_run' => $nextRunFormatted,
                'next_run_relative' => $nextRunRelative,
            ];
        })->sortBy('next_run')->values()->toArray();
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

    public function render(): View
    {
        return view('livewire.scheduled-tasks');
    }
}

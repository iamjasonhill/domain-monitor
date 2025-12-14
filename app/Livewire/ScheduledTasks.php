<?php

namespace App\Livewire;

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
        // Get scheduled tasks from Laravel's scheduler
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();

        $this->tasks = collect($events)->map(function ($event) {
            $description = $event->description ?? 'No description';
            $command = $event->command ?? 'N/A';
            $expression = $event->expression ?? 'N/A';
            $timezone = $event->timezone ?? config('app.timezone', 'UTC');

            // Parse the cron expression to get a human-readable schedule
            $scheduleText = $this->parseCronExpression($expression, $timezone);

            // Get next run time
            $nextRun = $event->nextRunDate();
            $nextRunFormatted = $nextRun ? $nextRun->format('Y-m-d H:i:s T') : 'N/A';
            $nextRunRelative = $nextRun ? $nextRun->diffForHumans() : 'N/A';

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

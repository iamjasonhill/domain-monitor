<?php

use App\Services\BrainEventClient;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth:api-key' => \App\Http\Middleware\AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Send error.exception events to Brain for unhandled exceptions
        $exceptions->report(function (Throwable $e) {
            try {
                $brain = app(BrainEventClient::class);
                $baseUrl = config('services.brain.base_url');
                $apiKey = config('services.brain.api_key');

                // Only send if Brain is configured
                if (empty($baseUrl) || empty($apiKey)) {
                    return;
                }

                $exceptionClass = get_class($e);
                $file = basename($e->getFile());
                $line = $e->getLine();

                // Determine severity
                $severity = 'error';
                if ($e instanceof \Illuminate\Http\Exceptions\HttpException) {
                    $statusCode = $e->getStatusCode();
                    if ($statusCode >= 500) {
                        $severity = 'critical';
                    }
                } elseif ($e instanceof \Error || $e instanceof \ParseError) {
                    $severity = 'critical';
                }

                // Create fingerprint
                $fingerprint = "error.exception:{$exceptionClass}:{$file}:{$line}";

                // Build context
                $context = [
                    'exception_class' => $exceptionClass,
                    'file' => $file,
                    'line' => $line,
                ];

                if (request()) {
                    $context['route'] = request()->path();
                    $context['method'] = request()->method();
                }

                // Build payload
                $payload = [
                    'exception' => $e->getMessage(),
                    'exception_class' => $exceptionClass,
                    'file' => $e->getFile(),
                    'line' => $line,
                    'trace' => $e->getTraceAsString(),
                ];

                // Send asynchronously (non-blocking)
                $brain->sendAsync('error.exception', $payload, [
                    'severity' => $severity,
                    'fingerprint' => $fingerprint,
                    'message' => $e->getMessage(),
                    'context' => $context,
                    'occurred_at' => now(),
                ]);
            } catch (\Exception $sendException) {
                // Don't let Brain event sending break exception handling
                Log::warning('Failed to send error.exception to Brain', [
                    'original_error' => $e->getMessage(),
                    'send_error' => $sendException->getMessage(),
                ]);
            }
        });
    })->create();

// Listen for failed queue jobs and send queue.failed events
Event::listen(JobFailed::class, function (JobFailed $event) {
    try {
        $brain = app(BrainEventClient::class);
        $baseUrl = config('services.brain.base_url');
        $apiKey = config('services.brain.api_key');

        // Only send if Brain is configured
        if (empty($baseUrl) || empty($apiKey)) {
            return;
        }

        $jobClass = get_class($event->job);
        $fingerprint = "queue.failed:{$jobClass}";

        $context = [
            'job_class' => $jobClass,
            'queue_name' => $event->connectionName ?? 'default',
            'attempts' => $event->job->attempts() ?? 1,
        ];

        $payload = [
            'job_id' => $event->job->getJobId(),
            'job_class' => $jobClass,
            'queue' => $event->connectionName ?? 'default',
            'attempts' => $event->job->attempts() ?? 1,
            'exception' => $event->exception->getMessage(),
            'exception_class' => get_class($event->exception),
        ];

        // Send asynchronously (non-blocking)
        $brain->sendAsync('queue.failed', $payload, [
            'severity' => 'error',
            'fingerprint' => $fingerprint,
            'message' => "Queue job {$jobClass} failed after {$context['attempts']} attempt(s)",
            'context' => $context,
            'occurred_at' => now(),
        ]);
    } catch (\Exception $e) {
        // Don't let Brain event sending break queue processing
        Log::warning('Failed to send queue.failed to Brain', [
            'job_class' => get_class($event->job),
            'error' => $e->getMessage(),
        ]);
    }
});

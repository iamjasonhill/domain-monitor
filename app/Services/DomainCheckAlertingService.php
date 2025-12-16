<?php

namespace App\Services;

use App\Models\DomainCheck;
use App\Models\DomainCheckAlertState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DomainCheckAlertingService
{
    private const int THRESHOLD = 3;

    /**
     * @var array<int, string>
     */
    private const array THREE_STRIKE_TYPES = ['http', 'ssl'];

    /**
     * @var array<int, string>
     */
    private const array FAILURE_STATUSES = ['warn', 'fail'];

    public function handle(DomainCheck $check): void
    {
        if (! in_array($check->check_type, self::THREE_STRIKE_TYPES, true)) {
            $check->emitBrainEvent();

            return;
        }

        DB::transaction(function () use ($check) {
            $state = $this->lockOrCreateState($check);

            $isFailure = in_array($check->status, self::FAILURE_STATUSES, true);
            if ($isFailure) {
                $state->consecutive_failure_count++;

                if (! $state->alert_active && $state->consecutive_failure_count >= self::THRESHOLD) {
                    $check->metadata = array_merge($check->metadata ?? [], [
                        'alert_state' => 'triggered',
                        'consecutive_failures' => $state->consecutive_failure_count,
                        'threshold' => self::THRESHOLD,
                    ]);

                    $check->emitBrainEvent();

                    $state->alert_active = true;
                    $state->alerted_at = now();
                }

                $state->save();

                return;
            }

            if ($state->alert_active) {
                $check->metadata = array_merge($check->metadata ?? [], [
                    'alert_state' => 'recovered',
                    'threshold' => self::THRESHOLD,
                ]);

                $check->emitBrainEvent();

                $state->alert_active = false;
                $state->recovered_at = now();
            }

            $state->consecutive_failure_count = 0;
            $state->save();
        });
    }

    private function lockOrCreateState(DomainCheck $check): DomainCheckAlertState
    {
        $existing = DomainCheckAlertState::query()
            ->where('domain_id', $check->domain_id)
            ->where('check_type', $check->check_type)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            DomainCheckAlertState::create([
                'domain_id' => $check->domain_id,
                'check_type' => $check->check_type,
                'consecutive_failure_count' => 0,
                'alert_active' => false,
                'alerted_at' => null,
                'recovered_at' => null,
            ]);
        } catch (QueryException $e) {
            // Another transaction may have created it due to the unique constraint.
        }

        return DomainCheckAlertState::query()
            ->where('domain_id', $check->domain_id)
            ->where('check_type', $check->check_type)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

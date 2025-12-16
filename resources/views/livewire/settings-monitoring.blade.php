<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Monitoring</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Tune how the dashboard and logs define “recent”.</p>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-800 dark:text-green-200 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <form wire:submit.prevent="save" class="space-y-6">
                <div>
                    <x-input-label for="recentFailuresHours" value="Recent failures window (hours)" />
                    <x-text-input
                        id="recentFailuresHours"
                        type="number"
                        min="1"
                        max="168"
                        wire:model="recentFailuresHours"
                        class="mt-1 block w-full"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Used by the dashboard “Recent Failures” widget and the “Recent” filters. 24 is a good default.
                    </p>
                    <x-input-error :messages="$errors->get('recentFailuresHours')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="pruneDomainChecksDays" value="Retain domain check history (days)" />
                        <x-text-input
                            id="pruneDomainChecksDays"
                            type="number"
                            min="1"
                            max="3650"
                            wire:model="pruneDomainChecksDays"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Used by the daily prune job for the `domain_checks` table.
                        </p>
                        <x-input-error :messages="$errors->get('pruneDomainChecksDays')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="pruneEligibilityChecksDays" value="Retain eligibility check history (days)" />
                        <x-text-input
                            id="pruneEligibilityChecksDays"
                            type="number"
                            min="1"
                            max="3650"
                            wire:model="pruneEligibilityChecksDays"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Used by the daily prune job for the `domain_eligibility_checks` table.
                        </p>
                        <x-input-error :messages="$errors->get('pruneEligibilityChecksDays')" class="mt-2" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit">
                        Save
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>



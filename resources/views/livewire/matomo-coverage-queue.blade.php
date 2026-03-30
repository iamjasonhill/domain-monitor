<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Matomo Coverage</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Review which web properties are eligible for Matomo, which ones are already covered, and which Matomo sites still need to be mapped back to Domain Monitor.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach([
            ['id' => 'eligible', 'label' => 'Eligible'],
            ['id' => 'needs_binding', 'label' => 'Needs Matomo'],
            ['id' => 'bound_attention', 'label' => 'Needs Attention'],
            ['id' => 'covered', 'label' => 'Covered'],
            ['id' => 'excluded', 'label' => 'Excluded'],
            ['id' => 'unmapped', 'label' => 'Unmapped Sites'],
        ] as $stat)
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xs p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats[$stat['id']] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Needs Matomo Binding</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $needsBinding->count() }} properties</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($needsBinding->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No eligible properties are currently missing a Matomo binding.</p>
                    @else
                        @foreach($needsBinding as $item)
                            <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-900/10 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['property']->name }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['primary_domain'] ?? 'No primary domain' }}</div>
                                    </div>
                                    <a href="{{ route('web-properties.show', $item['property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        Open property
                                    </a>
                                </div>
                                <div class="mt-2 text-sm text-amber-800 dark:text-amber-200">{{ $item['coverage']['reason'] }}</div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Bound But Needs Attention</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $boundAttention->count() }} properties</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($boundAttention->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No bound properties currently need attention.</p>
                    @else
                        @foreach($boundAttention as $item)
                            @php
                                $audit = $item['matomo_source']?->latestInstallAudit;
                            @endphp
                            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50/60 dark:bg-red-900/10 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['property']->name }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $item['primary_domain'] ?? 'No primary domain' }}
                                            @if($item['matomo_source'])
                                                · Matomo {{ $item['matomo_source']->external_id }}
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('web-properties.show', $item['property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        Open property
                                    </a>
                                </div>
                                <div class="mt-2 text-sm text-red-800 dark:text-red-200">{{ $item['coverage']['reason'] }}</div>
                                @if($audit?->best_url)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 break-all">{{ $audit->best_url }}</div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Covered</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $covered->count() }} properties</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($covered->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No covered properties yet.</p>
                    @else
                        @foreach($covered as $item)
                            @php
                                $audit = $item['matomo_source']?->latestInstallAudit;
                            @endphp
                            <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-900/10 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['property']->name }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $item['primary_domain'] ?? 'No primary domain' }}
                                            @if($item['matomo_source'])
                                                · Matomo {{ $item['matomo_source']->external_id }}
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('web-properties.show', $item['property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        Open property
                                    </a>
                                </div>
                                <div class="mt-2 text-sm text-emerald-800 dark:text-emerald-200">{{ $item['coverage']['reason'] }}</div>
                                @if($audit?->best_url)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 break-all">{{ $audit->best_url }}</div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Unmapped Matomo Sites</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $unmappedObservations->count() }} sites</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($unmappedObservations->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No unmapped Matomo sites from the latest imports.</p>
                    @else
                        @foreach($unmappedObservations as $item)
                            @php
                                $observation = $item['observation'];
                            @endphp
                            <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/10 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Matomo {{ $observation->external_id }} · {{ $observation->external_name ?? 'Unnamed site' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $observation->install_verdict) }}</div>
                                    </div>
                                    @if($item['suggested_property'])
                                        <a href="{{ route('web-properties.show', $item['suggested_property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                            Suggested: {{ $item['suggested_property']->name }}
                                        </a>
                                    @endif
                                </div>
                                @if($observation->best_url)
                                    <div class="mt-2 text-sm text-blue-800 dark:text-blue-200 break-all">{{ $observation->best_url }}</div>
                                @endif
                                @if($observation->summary)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $observation->summary }}</div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Excluded From Matomo</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $excluded->count() }} properties</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($excluded->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No excluded properties.</p>
                    @else
                        @foreach($excluded as $item)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['property']->name }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['primary_domain'] ?? 'No primary domain' }}</div>
                                    </div>
                                    <a href="{{ route('web-properties.show', $item['property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        Open property
                                    </a>
                                </div>
                                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $item['coverage']['reason'] }}</div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

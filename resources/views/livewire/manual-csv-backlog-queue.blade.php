<div class="space-y-6">
    @if (session('message'))
        <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-200">
            {{ session('message') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Legacy Manual Search Console CSV Archive</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Manual CSV evidence is legacy archive/backfill context only. Upload old Google Search Console Page indexing ZIP exports here only when preserving historical evidence, not to satisfy active automation coverage.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach([
            ['id' => 'pending_properties', 'label' => 'Legacy Missing Archives'],
            ['id' => 'pending_domains', 'label' => 'Affected Domains'],
        ] as $stat)
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xs p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats[$stat['id']] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Legacy Evidence Gaps</h4>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $pendingItems->count() }} properties</span>
            </div>

            <div class="mt-4 space-y-3">
                @if($pendingItems->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">No legacy manual Search Console CSV archive gaps are currently listed.</p>
                @else
                    @foreach($pendingItems as $item)
                        <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50/60 dark:bg-yellow-900/10 text-yellow-800 dark:text-yellow-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['property']->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item['primary_domain'] ?? 'No primary domain' }}
                                        @if(data_get($item, 'ga4_lookup.measurement_id'))
                                            · GA4 {{ data_get($item, 'ga4_lookup.measurement_id') }}
                                        @elseif(data_get($item, 'ga4_lookup.provisioning_state'))
                                            · {{ str(data_get($item, 'ga4_lookup.provisioning_state'))->replace('_', ' ')->title() }}
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('web-properties.show', $item['property']->slug) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Open property
                                </a>
                            </div>

                            <div class="mt-2 text-sm">{{ $item['legacy_manual_csv']['reason'] }}</div>

                            @if($item['repository'])
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Controller: {{ $item['repository']->repo_name }}
                                </div>
                            @endif

                            @if($item['search_console_coverage'])
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item['search_console_coverage']->mappingStateLabel() }}
                                    @if($item['search_console_coverage']->property_uri)
                                        · {{ $item['search_console_coverage']->property_uri }}
                                    @endif
                                    · {{ $item['search_console_coverage']->freshnessLabel() }}
                                </div>
                            @endif

                            @if($item['latest_baseline'])
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Latest baseline {{ $item['latest_baseline']->captured_at->format('Y-m-d') }}
                                    · {{ $item['latest_baseline']->importMethodLabel() }}
                                </div>
                            @endif

                            <div class="mt-4 rounded-md border border-yellow-200 dark:border-yellow-800/60 bg-white/70 dark:bg-gray-900/40 p-3">
                                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Import legacy Search Console export
                                </div>
                                <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-center">
                                    <input
                                        type="file"
                                        accept=".zip,application/zip"
                                        wire:model="evidenceArchives.{{ $item['property']->id }}"
                                        class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:rounded-md file:border-0 file:bg-yellow-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-yellow-700"
                                    />
                                    <button
                                        type="button"
                                        wire:click="importEvidence('{{ $item['property']->id }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="importEvidence('{{ $item['property']->id }}'), evidenceArchives.{{ $item['property']->id }}"
                                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Import ZIP
                                    </button>
                                </div>
                                @error('evidenceArchives.'.$item['property']->id)
                                    <div class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

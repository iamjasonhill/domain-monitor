<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Automation Coverage</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Track the full website automation checklist in one place: controller coverage, MM-Google GA4 readiness, Search Console onboarding, baseline sync, and optional manual CSV evidence.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-11 gap-4">
        @foreach([
            ['id' => 'required', 'label' => 'Required'],
            ['id' => 'needs_controller', 'label' => 'Needs Controller'],
            ['id' => 'needs_ga4_sync', 'label' => 'Needs GA4'],
            ['id' => 'ga4_provisioning', 'label' => 'GA4 Provisioning'],
            ['id' => 'ga4_attention', 'label' => 'GA4 Attention'],
            ['id' => 'needs_search_console_mapping', 'label' => 'Needs SC'],
            ['id' => 'needs_onboarding', 'label' => 'Needs Onboarding'],
            ['id' => 'import_stale', 'label' => 'Import Stale'],
            ['id' => 'needs_baseline_sync', 'label' => 'Needs Baseline'],
            ['id' => 'manual_csv_pending', 'label' => 'CSV Pending'],
            ['id' => 'complete', 'label' => 'Complete'],
        ] as $stat)
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xs p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats[$stat['id']] }}</div>
            </div>
        @endforeach
    </div>

    @php
        $sections = [
            ['title' => 'Needs Controller', 'items' => $needsController, 'tone' => 'amber'],
            ['title' => 'Needs GA4 Sync', 'items' => $needsGa4Sync, 'tone' => 'red'],
            ['title' => 'GA4 Provisioning', 'items' => $ga4Provisioning, 'tone' => 'amber'],
            ['title' => 'GA4 Attention', 'items' => $ga4Attention, 'tone' => 'red'],
            ['title' => 'Needs Search Console Mapping', 'items' => $needsSearchConsoleMapping, 'tone' => 'blue'],
            ['title' => 'Needs Onboarding', 'items' => $needsOnboarding, 'tone' => 'violet'],
            ['title' => 'Import Stale', 'items' => $importStale, 'tone' => 'red'],
            ['title' => 'Needs Baseline Sync', 'items' => $needsBaselineSync, 'tone' => 'indigo'],
            ['title' => 'Manual CSV Pending', 'items' => $manualCsvPending, 'tone' => 'yellow'],
            ['title' => 'Complete', 'items' => $complete, 'tone' => 'emerald'],
            ['title' => 'Excluded', 'items' => $excluded, 'tone' => 'gray'],
        ];
    @endphp

    @foreach($sections as $section)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $section['title'] }}</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $section['items']->count() }} properties</span>
                </div>

                <div class="mt-4 space-y-3">
                    @if($section['items']->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No properties in this queue right now.</p>
                    @else
                        @foreach($section['items'] as $item)
                            @php
                                $toneClasses = match ($section['tone']) {
                                    'amber' => 'border-amber-200 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-900/10 text-amber-800 dark:text-amber-200',
                                    'blue' => 'border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/10 text-blue-800 dark:text-blue-200',
                                    'red' => 'border-red-200 dark:border-red-800 bg-red-50/60 dark:bg-red-900/10 text-red-800 dark:text-red-200',
                                    'violet' => 'border-violet-200 dark:border-violet-800 bg-violet-50/60 dark:bg-violet-900/10 text-violet-800 dark:text-violet-200',
                                    'indigo' => 'border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-900/10 text-indigo-800 dark:text-indigo-200',
                                    'yellow' => 'border-yellow-200 dark:border-yellow-800 bg-yellow-50/60 dark:bg-yellow-900/10 text-yellow-800 dark:text-yellow-200',
                                    'emerald' => 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-900/10 text-emerald-800 dark:text-emerald-200',
                                    default => 'border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/10 text-gray-700 dark:text-gray-300',
                                };
                            @endphp
                            <div class="rounded-lg border p-4 {{ $toneClasses }}">
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

                                <div class="mt-2 text-sm">{{ $item['automation']['reason'] }}</div>

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
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

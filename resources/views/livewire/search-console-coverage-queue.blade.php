<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Search Console Coverage</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Review which MM-Google-synced properties still need Search Console, which ones are still on URL-prefix mappings, and which ones are ready for baseline capture or rebuild work.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-7 gap-4">
        @foreach([
            ['id' => 'eligible', 'label' => 'Eligible'],
            ['id' => 'needs_search_console', 'label' => 'Needs SC'],
            ['id' => 'url_prefix_only', 'label' => 'URL Prefix'],
            ['id' => 'stale_imports', 'label' => 'Import Stale'],
            ['id' => 'needs_baseline', 'label' => 'Needs Baseline'],
            ['id' => 'domain_property_ready', 'label' => 'Domain Ready'],
            ['id' => 'excluded', 'label' => 'Excluded'],
        ] as $stat)
            <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xs p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats[$stat['id']] }}</div>
            </div>
        @endforeach
    </div>

    @php
        $sections = [
            ['title' => 'Needs Search Console', 'items' => $needsSearchConsole, 'tone' => 'amber'],
            ['title' => 'URL Prefix Only', 'items' => $urlPrefixOnly, 'tone' => 'blue'],
            ['title' => 'Import Stale', 'items' => $staleImports, 'tone' => 'red'],
            ['title' => 'Needs Baseline', 'items' => $needsBaseline, 'tone' => 'violet'],
            ['title' => 'Domain Property Ready', 'items' => $domainPropertyReady, 'tone' => 'emerald'],
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
                                $tone = $section['tone'];
                                $toneClasses = match ($tone) {
                                    'amber' => 'border-amber-200 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-900/10 text-amber-800 dark:text-amber-200',
                                    'blue' => 'border-blue-200 dark:border-blue-800 bg-blue-50/60 dark:bg-blue-900/10 text-blue-800 dark:text-blue-200',
                                    'red' => 'border-red-200 dark:border-red-800 bg-red-50/60 dark:bg-red-900/10 text-red-800 dark:text-red-200',
                                    'violet' => 'border-violet-200 dark:border-violet-800 bg-violet-50/60 dark:bg-violet-900/10 text-violet-800 dark:text-violet-200',
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

                                @if($item['coverage'])
                                    <div class="mt-2 text-sm">
                                        {{ $item['coverage']->mappingStateLabel() }}
                                        @if($item['coverage']->property_uri)
                                            · {{ $item['coverage']->property_uri }}
                                        @endif
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Freshness: {{ $item['coverage']->freshnessLabel() }}
                                        @if($item['coverage']->latest_metric_date)
                                            · Latest data {{ $item['coverage']->latest_metric_date->format('Y-m-d') }}
                                        @endif
                                    </div>
                                @elseif($item['ga4_source'])
                                    <div class="mt-2 text-sm">MM-Google GA4 is synced, but no Search Console mapping has been synced into Domain Monitor yet.</div>
                                @else
                                    <div class="mt-2 text-sm">GA4 is not synced from MM-Google yet, so Search Console cannot be treated as ready for this property.</div>
                                @endif

                                @if($item['latest_baseline'])
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Latest SEO baseline {{ $item['latest_baseline']->captured_at->format('Y-m-d') }}
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

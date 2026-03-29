@php
    $latestSeoBaseline = $domain->seoBaselines->first();
    $seoBaselines = $domain->seoBaselines;
@endphp

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">SEO Baselines</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Milestone snapshots for search visibility and indexation. Capture these before rebuilds, cutovers, and major cleanup work.
                </p>
            </div>
            @if($latestSeoBaseline)
                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                    Latest {{ $latestSeoBaseline->baselineTypeLabel() }}
                </span>
            @endif
        </div>

        @if($latestSeoBaseline)
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/40">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Clicks</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($latestSeoBaseline->clicks, 0) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/40">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Impressions</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($latestSeoBaseline->impressions, 0) }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/40">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">CTR</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($latestSeoBaseline->ctr * 100, 2) }}%</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/40">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Position</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($latestSeoBaseline->average_position, 2) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Snapshot</h4>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Captured</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->captured_at->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Window</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">
                                @if($latestSeoBaseline->date_range_start && $latestSeoBaseline->date_range_end)
                                    {{ $latestSeoBaseline->date_range_start->format('Y-m-d') }} to {{ $latestSeoBaseline->date_range_end->format('Y-m-d') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Import</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->importMethodLabel() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Matomo Site</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->matomo_site_id ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Search Type</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ strtoupper($latestSeoBaseline->search_type) }}</dd>
                        </div>
                        @if($latestSeoBaseline->webProperty?->name)
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">Web Property</dt>
                                <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->webProperty->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Indexation</h4>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Indexed Pages</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->indexed_pages ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Not Indexed Pages</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->not_indexed_pages ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Inspected URLs</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->inspected_url_count ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Inspection Indexed</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->inspection_indexed_url_count ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Inspection Not Indexed</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->inspection_non_indexed_url_count ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">Coverage Signals</h4>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">AMP URLs</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->amp_urls ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Mobile Issue URLs</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->mobile_issue_urls ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Rich Result URLs</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->rich_result_urls ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Rich Result Issue URLs</dt>
                            <dd class="text-right text-gray-900 dark:text-gray-100">{{ $latestSeoBaseline->rich_result_issue_urls ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @php
                $nonZeroIssues = $latestSeoBaseline->nonZeroIndexationIssues();
            @endphp
            @if($nonZeroIssues !== [])
                <div class="mt-6">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-3">Current Issue Counts</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($nonZeroIssues as $issue)
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                {{ $issue['label'] }}: {{ $issue['value'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($latestSeoBaseline->notes)
                <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-gray-700/40 dark:text-gray-300">
                    {{ $latestSeoBaseline->notes }}
                </div>
            @endif

            <div class="mt-6">
                <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-3">Recent Checkpoints</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Captured</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Window</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Clicks</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Impressions</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Indexed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Not Indexed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($seoBaselines as $baseline)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        {{ $baseline->captured_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        {{ $baseline->baselineTypeLabel() }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        @if($baseline->date_range_start && $baseline->date_range_end)
                                            {{ $baseline->date_range_start->format('Y-m-d') }} to {{ $baseline->date_range_end->format('Y-m-d') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ number_format($baseline->clicks, 0) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ number_format($baseline->impressions, 0) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $baseline->indexed_pages ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $baseline->not_indexed_pages ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No SEO baseline snapshot has been stored for this domain yet. Capture the Search Console baseline in Matomo first, then sync the milestone snapshot into domain-monitor before rebuild work starts.
            </div>
        @endif
    </div>
</div>

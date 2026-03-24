<div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-5">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Properties</dt>
                <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->stats['total'] }}</dd>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-5">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Multi-Domain</dt>
                <dd class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->stats['multi_domain'] }}</dd>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-5">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Missing Repo</dt>
                <dd class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ $this->stats['missing_repositories'] }}</dd>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-5">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Missing Analytics</dt>
                <dd class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ $this->stats['missing_analytics'] }}</dd>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <x-text-input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Search property name, slug, domain, or repo..."
                    />
                </div>

                <div class="w-full lg:w-48">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select wire:model.live="filterStatus"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="planned">Planned</option>
                        <option value="paused">Paused</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="w-full lg:w-56">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Property Type</label>
                    <select wire:model.live="filterType"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Types</option>
                        @foreach($this->availablePropertyTypes as $type)
                            <option value="{{ $type }}">{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-4">
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="reviewQueue"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Review Queue</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="multiDomainOnly"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Multi-Domain Only</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="missingRepoOnly"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Missing Repo Link</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="missingAnalyticsOnly"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Missing Analytics Link</span>
                </label>
            </div>

            <div class="mt-4">
                <button wire:click="clearFilters"
                    class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600">
                    Clear Filters
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
            @if($properties->count() > 0)
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-700/20">
                    <div class="flex flex-col gap-2 text-xs text-gray-500 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            The colored dot shows <span class="font-semibold text-gray-700 dark:text-gray-300">registry review status</span>, not site health.
                            Use the <span class="font-semibold text-gray-700 dark:text-gray-300">Health</span> column for monitoring status.
                        </p>
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                Registry looks linked cleanly
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                Review links or grouping
                            </span>
                        </div>
                    </div>
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Property</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primary Domain</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Coverage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Health</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Review Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($properties as $property)
                            @php
                                $health = $property->healthSummary();
                                $isReviewCandidate = $property->property_domains_count > 1 || $property->repositories_count === 0 || $property->analytics_sources_count === 0;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/25">
                                <td class="px-6 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-1">
                                            @if($isReviewCandidate)
                                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-amber-500" title="Registry review needed: missing links or multi-domain grouping to check"></span>
                                            @else
                                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500" title="Registry linked cleanly"></span>
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('web-properties.show', $property->slug) }}" wire:navigate class="text-sm font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ $property->name }}
                                            </a>
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $property->slug }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                                    {{ str_replace('_', ' ', ucfirst($property->property_type)) }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    {{ ucfirst($property->status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $property->primaryDomainName() ?? 'Not set' }}
                                    </div>
                                    @if($property->production_url)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 break-all">{{ $property->production_url }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                        <div>Domains: <span class="font-semibold">{{ $property->property_domains_count }}</span></div>
                                        <div>Repos: <span class="font-semibold">{{ $property->repositories_count }}</span></div>
                                        <div>Analytics: <span class="font-semibold">{{ $property->analytics_sources_count }}</span></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="space-y-1">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $health['overall_status'] === 'ok',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $health['overall_status'] === 'warn',
                                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $health['overall_status'] === 'fail',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $health['overall_status'] === 'unknown',
                                        ])>
                                            {{ strtoupper($health['overall_status']) }}
                                        </span>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Alerts: {{ $health['active_alerts_count'] }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if($property->repositories_count === 0)
                                            <div>No repository linked yet.</div>
                                        @endif
                                        @if($property->analytics_sources_count === 0)
                                            <div>No analytics source linked yet.</div>
                                        @endif
                                        @if($property->property_domains_count > 1)
                                            <div>Has {{ $property->property_domains_count }} linked domains. Review alias grouping.</div>
                                        @endif
                                        @if($property->repositories_count > 0 && $property->analytics_sources_count > 0 && $property->property_domains_count <= 1)
                                            <div>Baseline linking looks clean.</div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="px-6 py-4">
                    {{ $properties->links() }}
                </div>
            @else
                <div class="p-6 text-center">
                    <p class="text-gray-500 dark:text-gray-400">No web properties found for the current filters.</p>
                </div>
            @endif
        </div>
    </div>
</div>

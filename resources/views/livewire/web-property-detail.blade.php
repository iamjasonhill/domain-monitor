<div>
    @if($property)
        @php
            $health = $property->healthSummary();
            $domains = $property->orderedDomainLinks();
            $repositories = $property->repositories;
            $analyticsSources = $property->analyticsSources;
            $tags = collect($property->tagSummaries());
        @endphp

        <div class="mb-6">
            <a href="{{ route('web-properties.index') }}" wire:navigate class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to web properties
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $property->name }}</h3>
                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ $property->slug }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                {{ str_replace('_', ' ', ucfirst($property->property_type)) }}
                            </span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ ucfirst($property->status) }}
                            </span>
                            @if($property->platform)
                                <span class="inline-flex items-center rounded-full bg-purple-50 dark:bg-purple-900/30 px-2.5 py-0.5 text-xs font-medium text-purple-700 dark:text-purple-300">
                                    {{ $property->platform }}
                                </span>
                            @endif
                        </div>
                        @if($property->notes)
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">{{ $property->notes }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 min-w-[260px]">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Domains</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $domains->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Repos</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $repositories->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Analytics</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analyticsSources->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Alerts</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $health['active_alerts_count'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Property Summary</h4>
                    <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Primary Domain</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->primaryDomainName() ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Production URL</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100 break-all">{{ $property->production_url ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Staging URL</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100 break-all">{{ $property->staging_url ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Owner</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->owner ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Priority</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->priority ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Overall Health</dt>
                            <dd class="mt-1">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $health['overall_status'] === 'ok',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $health['overall_status'] === 'warn',
                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $health['overall_status'] === 'fail',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $health['overall_status'] === 'unknown',
                                ])>
                                    {{ strtoupper($health['overall_status']) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tags</h4>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if($tags->isEmpty())
                            <span class="text-sm text-gray-500 dark:text-gray-400">No tags linked through domains yet.</span>
                        @else
                            @foreach($tags as $tag)
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                    style="background-color: {{ $tag['color'] ?? '#6B7280' }}">
                                    {{ $tag['name'] }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Linked Domains</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $domains->count() }} linked</span>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">State</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Health</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($domains as $link)
                                @php
                                    $domain = $link->domain;
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $domain?->domain ?? 'Missing domain' }}</span>
                                            @if($link->is_canonical)
                                                <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                                    Canonical
                                                </span>
                                            @endif
                                            @if($domain && ! $domain->is_active)
                                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Inactive
                                                </span>
                                            @endif
                                        </div>
                                        @if($domain?->dns_config_name)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $domain->dns_config_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        {{ ucfirst($link->usage_type) }}
                                        @if($link->notes)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $link->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        <div>{{ $domain?->platform ?? 'Unknown platform' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Alerts: {{ $domain?->alerts?->count() ?? 0 }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($domain)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach(['http', 'ssl', 'dns', 'seo'] as $checkType)
                                                    @php
                                                        $status = $domain->{'latest_'.$checkType.'_status'} ?? 'unknown';
                                                    @endphp
                                                    <span @class([
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $status === 'ok',
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $status === 'warn',
                                                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $status === 'fail',
                                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $status === 'unknown',
                                                    ])>
                                                        {{ strtoupper($checkType) }} {{ strtoupper($status) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($domain)
                                            <a href="{{ route('domains.show', $domain->id) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                                Open domain
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Repositories</h4>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $repositories->count() }} linked</span>
                    </div>
                    <div class="mt-4 space-y-4">
                        @if($repositories->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">No repositories linked yet.</p>
                        @else
                            @foreach($repositories as $repository)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $repository->repo_name }}</div>
                                        @if($repository->is_primary)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Primary
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <div>Framework: {{ $repository->framework ?? 'Unknown' }}</div>
                                        <div>Provider: {{ $repository->repo_provider }}</div>
                                        <div class="break-all">Path: {{ $repository->local_path ?? 'Not set' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Analytics Sources</h4>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $analyticsSources->count() }} linked</span>
                    </div>
                    <div class="mt-4 space-y-4">
                        @if($analyticsSources->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">No analytics sources linked yet.</p>
                        @else
                            @foreach($analyticsSources as $source)
                                @php
                                    $installAudit = $source->latestInstallAudit;
                                    $installVerdict = $installAudit?->install_verdict;
                                @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ strtoupper($source->provider) }}: {{ $source->external_id }}</div>
                                        @if($source->is_primary)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Primary
                                            </span>
                                        @endif
                                        @if($installVerdict)
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $installVerdict === 'installed_match',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($installVerdict, ['partial_detection', 'unknown'], true),
                                                'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => in_array($installVerdict, ['not_detected', 'installed_wrong_site_id', 'installed_other_tracker_host', 'fetch_failed'], true),
                                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($installVerdict, ['installed_match', 'partial_detection', 'unknown', 'not_detected', 'installed_wrong_site_id', 'installed_other_tracker_host', 'fetch_failed'], true),
                                            ])>
                                                {{ str_replace('_', ' ', $installVerdict) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <div>Name: {{ $source->external_name ?? 'Not set' }}</div>
                                        <div>Status: {{ ucfirst($source->status) }}</div>
                                        <div class="break-all">Workspace: {{ $source->workspace_path ?? 'Not set' }}</div>
                                        @if($installAudit)
                                            <div>Tracker: {{ $installAudit->expected_tracker_host ?? 'Unknown' }}</div>
                                            <div>Checked: {{ $installAudit->checked_at?->diffForHumans() ?? 'Unknown' }}</div>
                                            @if($installAudit->best_url)
                                                <div class="break-all">Best URL: {{ $installAudit->best_url }}</div>
                                            @endif
                                            @if($installAudit->summary)
                                                <div>{{ $installAudit->summary }}</div>
                                            @endif
                                        @else
                                            <div>No install audit imported yet.</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

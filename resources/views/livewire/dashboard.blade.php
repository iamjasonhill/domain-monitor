<div wire:poll.120s.keep-alive>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Domains -->
        <a href="{{ route('domains.index') }}" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-blue-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Total Domains</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total_domains'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Active Domains -->
        <a href="{{ route('domains.index') }}?active=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Active Domains</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['active_domains'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Expiring Soon -->
        <a href="{{ route('domains.index') }}?expiring=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Expiring Soon (30 days)</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['expiring_soon'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Recent Failures -->
        <a href="{{ route('health-checks.index') }}?recentFailures=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Recent Failures ({{ $recentFailuresHours }} hours)</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['recent_failures'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Failed Eligibility Status -->
        <a href="{{ route('eligibility-checks.index') }}?failed=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-orange-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Failed Eligibility</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['failed_eligibility'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Must Fix -->
        <a href="{{ route('dashboard') }}#detected-must-fix" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-red-700 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3l-8.47-14.14a2 2 0 00-3.42 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Must Fix</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['detected_must_fix'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Should Fix -->
        <a href="{{ route('dashboard') }}#should-fix" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-amber-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Should Fix</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['should_fix'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Manual CSV Backlog -->
        <a href="{{ route('dashboard') }}#manual-csv-backlog" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-yellow-600 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Manual CSV Backlog</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['manual_csv_pending'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Unresolved Web Subdomains -->
        <a href="{{ route('dashboard') }}#subdomain-cleanup" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="shrink-0 bg-rose-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium leading-snug text-gray-500 dark:text-gray-400">Unresolved Web Subdomains</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['unresolved_web_subdomains'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

    </div>

    <!-- Detected Issue Feed -->
    <div id="detected-must-fix" class="mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Detected Must Fix</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Live monitoring, marketing, Search Console, and queue findings that currently rank as urgent.</p>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['detected_must_fix'] }}</div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Open Issues</div>
                    </div>
                </div>

                @if($detectedMustFixIssues->isNotEmpty())
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($detectedMustFixIssues as $issue)
                            <div class="rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/20 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ $issue['domain'] ?? $issue['property_name'] ?? $issue['property_slug'] ?? 'Unknown property' }}
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $issue['issue_class'] ?? 'detected_issue' }}
                                            @if(!empty($issue['detector']))
                                                · {{ $issue['detector'] }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        Must fix
                                    </span>
                                </div>

                                <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ data_get($issue, 'evidence.primary_reasons.0') ?? data_get($issue, 'evidence.summary') ?? $issue['summary'] ?? 'Detected issue needs review.' }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if(!empty($issue['property_slug']))
                                        <a href="{{ route('web-properties.show', $issue['property_slug']) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">Open property</a>
                                    @endif
                                    @if(!empty($issue['detected_at']))
                                        <span>Detected {{ $issue['detected_at'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-green-200 dark:border-green-900/60 bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">No urgent detected issues are currently flagged.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Priority Queue -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <div id="must-fix" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Must Fix</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Hard failures, critical alerts, or compliance issues that should be handled first.</p>
                    </div>
                    <a href="{{ route('alerts.index') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View alerts</a>
                </div>

                @if($mustFixDomains->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($mustFixDomains as $item)
                            <div class="border border-red-200 dark:border-red-900/60 rounded-lg p-4 bg-red-50/50 dark:bg-red-950/20">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <a href="{{ route('domains.show', $item['id']) }}" wire:navigate class="text-base font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $item['domain'] }}
                                        </a>
                                        @if($item['hosting_provider'])
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $item['hosting_provider'] }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        {{ $item['primary_reason_count'] }} issue{{ $item['primary_reason_count'] === 1 ? '' : 's' }}
                                    </span>
                                </div>

                                <ul class="mt-4 space-y-2">
                                    @foreach($item['primary_reasons'] as $reason)
                                        <li class="text-sm text-gray-700 dark:text-gray-300 flex items-start gap-2">
                                            <span class="mt-1 h-2 w-2 rounded-full bg-red-500 shrink-0"></span>
                                            <span>{{ $reason }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if(!empty($item['secondary_reasons']))
                                    <div class="mt-4 pt-4 border-t border-red-200 dark:border-red-900/60">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Also Review</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($item['secondary_reasons'] as $reason)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {{ $reason }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Updated {{ $item['updated_at_human'] ?? 'recently' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-green-200 dark:border-green-900/60 bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">No urgent domain issues are currently flagged.</p>
                    </div>
                @endif
            </div>
        </div>

        <div id="should-fix" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Should Fix</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Warnings and degradations that deserve attention, but are not the most urgent blockers.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="refreshShouldFixQueue" wire:loading.attr="disabled" wire:target="refreshShouldFixQueue" class="inline-flex items-center px-3 py-1.5 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="refreshShouldFixQueue">Refresh Current Issues</span>
                            <span wire:loading wire:target="refreshShouldFixQueue">Refreshing...</span>
                        </button>
                        <a href="{{ route('health-checks.index') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View health checks</a>
                    </div>
                </div>

                @if($shouldFixDomains->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($shouldFixDomains as $item)
                            @php
                                $visibleReasons = $item['primary_reasons'] !== []
                                    ? $item['primary_reasons']
                                    : $item['secondary_reasons'];
                                $visibleReasonCount = count($visibleReasons);
                            @endphp
                            <div class="border border-amber-200 dark:border-amber-900/60 rounded-lg p-4 bg-amber-50/50 dark:bg-amber-950/20">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <a href="{{ route('domains.show', $item['id']) }}" wire:navigate class="text-base font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $item['domain'] }}
                                        </a>
                                        @if($item['hosting_provider'])
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $item['hosting_provider'] }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                        {{ $visibleReasonCount }} issue{{ $visibleReasonCount === 1 ? '' : 's' }}
                                    </span>
                                </div>

                                <ul class="mt-4 space-y-2">
                                    @foreach($visibleReasons as $reason)
                                        <li class="text-sm text-gray-700 dark:text-gray-300 flex items-start gap-2">
                                            <span class="mt-1 h-2 w-2 rounded-full bg-amber-500 shrink-0"></span>
                                            <span>{{ $reason }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Updated {{ $item['updated_at_human'] ?? 'recently' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-green-200 dark:border-green-900/60 bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">No non-urgent domain issues are currently flagged.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Manual CSV Backlog -->
    <div id="manual-csv-backlog" class="mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Manual Search Console CSV Backlog</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Automation is done for these properties, but manual Search Console CSV evidence still needs to be uploaded.</p>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $manualCsvPendingStats['pending_properties'] }}</div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending Properties</div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 mb-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $manualCsvPendingStats['pending_domains'] }} domain{{ $manualCsvPendingStats['pending_domains'] === 1 ? '' : 's' }} currently need manual evidence.
                    </div>
                    <a href="{{ route('manual-csv-backlog.index') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Open full backlog
                    </a>
                </div>

                @if($manualCsvPendingItems->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($manualCsvPendingItems as $item)
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

                                <div class="mt-2 text-sm">{{ $item['automation']['reason'] }}</div>

                                @if($item['latest_baseline'])
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Latest baseline {{ $item['latest_baseline']->captured_at->format('Y-m-d') }}
                                        · {{ $item['latest_baseline']->importMethodLabel() }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-green-200 dark:border-green-900/60 bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">No properties are currently waiting on manual Search Console CSV evidence.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Subdomain Cleanup -->
    <div id="subdomain-cleanup" class="mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Unresolved Web Subdomains</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">These are the cleanup candidates worth reviewing for stale web DNS.</p>
                    </div>
                </div>

                @if($unresolvedWebSubdomainDomains->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($unresolvedWebSubdomainDomains as $item)
                            <div class="border border-rose-200 dark:border-rose-900/60 rounded-lg p-4 bg-rose-50/50 dark:bg-rose-950/20">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('domains.show', $item['domain_id']) }}" wire:navigate class="text-base font-semibold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ $item['domain'] }}
                                    </a>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200">
                                        {{ $item['count'] }} host{{ $item['count'] === 1 ? '' : 's' }}
                                    </span>
                                </div>
                                <ul class="mt-3 space-y-1">
                                    @foreach($item['hosts'] as $host)
                                        <li class="text-sm text-gray-700 dark:text-gray-300 font-mono">{{ $host }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-green-200 dark:border-green-900/60 bg-green-50 dark:bg-green-950/20 p-4">
                        <p class="text-sm text-green-800 dark:text-green-200">No unresolved web subdomains are currently tracked.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-6 mb-8">
        <!-- Total Domains -->
        <a href="{{ route('domains.index') }}" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Domains</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total_domains'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Active Domains -->
        <a href="{{ route('domains.index') }}?active=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Active Domains</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['active_domains'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Expiring Soon -->
        <a href="{{ route('domains.index') }}?expiring=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Expiring Soon (30 days)</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['expiring_soon'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Recent Failures -->
        <a href="{{ route('health-checks.index') }}?recentFailures=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Recent Failures ({{ $recentFailuresHours }} hours)</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['recent_failures'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Failed Eligibility Status -->
        <a href="{{ route('eligibility-checks.index') }}?failed=1" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-orange-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Failed Eligibility</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['failed_eligibility'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Must Fix -->
        <a href="{{ route('dashboard') }}#must-fix" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-700 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3l-8.47-14.14a2 2 0 00-3.42 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Must Fix</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['must_fix'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>

        <!-- Should Fix -->
        <a href="{{ route('dashboard') }}#should-fix" wire:navigate class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow cursor-pointer">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-amber-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Should Fix</dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['should_fix'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Priority Queue -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <div id="must-fix" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
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
                                            <span class="mt-1 h-2 w-2 rounded-full bg-red-500 flex-shrink-0"></span>
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

        <div id="should-fix" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Should Fix</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Warnings and degradations that deserve attention, but are not the most urgent blockers.</p>
                    </div>
                    <a href="{{ route('health-checks.index') }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View health checks</a>
                </div>

                @if($shouldFixDomains->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($shouldFixDomains as $item)
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
                                        {{ $item['primary_reason_count'] }} issue{{ $item['primary_reason_count'] === 1 ? '' : 's' }}
                                    </span>
                                </div>

                                <ul class="mt-4 space-y-2">
                                    @foreach($item['primary_reasons'] as $reason)
                                        <li class="text-sm text-gray-700 dark:text-gray-300 flex items-start gap-2">
                                            <span class="mt-1 h-2 w-2 rounded-full bg-amber-500 flex-shrink-0"></span>
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

    <!-- Quick Actions -->
    <div class="mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h3>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('domains.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        View All Domains
                    </a>
                    <a href="{{ route('domains.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Add New Domain
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Domains -->
    <div class="mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Domains</h3>
                @if($recentDomains->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Updated</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentDomains as $domain)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('domains.show', $domain->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $domain->domain }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($domain->is_active)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($domain->expires_at)
                                                {{ $domain->expires_at->format('Y-m-d') }}
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $domain->updated_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">No domains yet. <a href="{{ route('domains.create') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Add your first domain</a></p>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Health Checks -->
    <div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Health Checks</h3>
                @if($recentChecks->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentChecks as $check)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $check->domain->domain }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span class="uppercase">{{ $check->check_type }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($check->status === 'ok')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">OK</span>
                                            @elseif($check->status === 'warn')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Warn</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Fail</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $check->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">No health checks yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

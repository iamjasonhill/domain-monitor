<div>
    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <x-text-input wire:model.live.debounce.300ms="search" type="text" class="mt-1 block w-full" placeholder="Search by domain..." />
                </div>

                <!-- Domain Filter -->
                <div>
                    <select wire:model.live="filterDomain" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Domains</option>
                        @foreach($domains as $domain)
                            <option value="{{ $domain->id }}">{{ $domain->domain }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <select wire:model.live="filterType" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="compliance_issue">Compliance Issue</option>
                        <option value="renewal_required">Renewal Required</option>
                        <option value="domain_expiring">Domain Expiring</option>
                        <option value="ssl_expiring">SSL Expiring</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Severity Filter -->
                <div>
                    <select wire:model.live="filterSeverity" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Severities</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <!-- Unresolved Filter -->
                <div>
                    <label class="flex items-center mt-3">
                        <input type="checkbox" wire:model.live="filterUnresolved" class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Show Unresolved Only</span>
                    </label>
                </div>
            </div>

            @if($search || $filterDomain || $filterType || $filterSeverity || !$filterUnresolved)
                <div class="mt-4">
                    <button wire:click="clearFilters" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Alerts Table -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            @if($alerts->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Triggered</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($alerts as $alert)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('domains.show', $alert->domain->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                            {{ $alert->domain->domain }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        @if($alert->alert_type === 'compliance_issue')
                                            Compliance Issue
                                        @elseif($alert->alert_type === 'renewal_required')
                                            Renewal Required
                                        @elseif($alert->alert_type === 'domain_expiring')
                                            Domain Expiring
                                        @elseif($alert->alert_type === 'ssl_expiring')
                                            SSL Expiring
                                        @else
                                            {{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded @if($alert->severity === 'critical' || $alert->severity === 'error') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @elseif($alert->severity === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @else bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @endif uppercase">
                                            {{ $alert->severity }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        @if($alert->payload)
                                            @php
                                                $payload = $alert->payload;
                                            @endphp
                                            @if(isset($payload['reason']))
                                                <div><strong>Reason:</strong> {{ $payload['reason'] }}</div>
                                            @endif
                                            @if(isset($payload['days_until_expiry']))
                                                <div><strong>Days:</strong> {{ $payload['days_until_expiry'] }}</div>
                                            @endif
                                            @if(isset($payload['expires_at']))
                                                <div><strong>Expires:</strong> {{ \Carbon\Carbon::parse($payload['expires_at'])->format('Y-m-d') }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $alert->triggered_at->diffForHumans() }}
                                        <div class="text-xs text-gray-400">{{ $alert->triggered_at->format('Y-m-d H:i:s') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($alert->resolved_at)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Resolved</span>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $alert->resolved_at->diffForHumans() }}</div>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Active</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $alerts->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400">No alerts found.</p>
                </div>
            @endif
        </div>
    </div>
</div>

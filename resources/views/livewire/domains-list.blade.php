<div wire:id="domains-list">
    <!-- Flash Message -->
    <div x-data="{ showFlash: false, flashMessage: '', flashType: '' }" @flash-message.window="
            showFlash = true;
            flashMessage = $event.detail.message || 'Operation completed';
            flashType = $event.detail.type || 'success';
            setTimeout(() => showFlash = false, 5000);
         " x-show="showFlash" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-full"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-full"
        class="fixed bottom-4 right-4 z-50 w-full max-w-sm" style="display: none;">
        <div :class="{
            'bg-green-500': flashType === 'success',
            'bg-yellow-500': flashType === 'warning',
            'bg-red-500': flashType === 'error'
        }" class="rounded-lg shadow-lg p-4 text-white flex items-center justify-between">
            <span x-text="flashMessage"></span>
            <button @click="showFlash = false" class="ml-4 text-white hover:text-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Platform Detection Actions -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Platform Detection</h3>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <!-- Detect Platforms -->
                <button wire:click="detectPlatforms" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center px-4 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span wire:loading.remove wire:target="detectPlatforms">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                            </path>
                        </svg>
                        Detect Platforms (All Domains)
                    </span>
                    <span wire:loading wire:target="detectPlatforms" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Detecting...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Domain Sync Actions -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Domain Sync Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Sync Domain Information -->
                <button wire:click="syncSynergyExpiry" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center px-4 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span wire:loading.remove wire:target="syncSynergyExpiry">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                        Sync Domain Info
                    </span>
                    <span wire:loading wire:target="syncSynergyExpiry" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Syncing...
                    </span>
                </button>

                <!-- Sync DNS Records -->
                <button wire:click="syncDnsRecords" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span wire:loading.remove wire:target="syncDnsRecords">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        Sync DNS Records
                    </span>
                    <span wire:loading wire:target="syncDnsRecords" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Syncing...
                    </span>
                </button>

                <!-- Import Domains -->
                <button wire:click="importSynergyDomains" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center px-4 py-3 bg-purple-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <span wire:loading.remove wire:target="importSynergyDomains">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
                        </svg>
                        Import Domains
                    </span>
                    <span wire:loading wire:target="importSynergyDomains" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Importing...
                    </span>
                </button>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                These actions will sync data for all Australian TLD domains (.com.au, .net.au, etc.) in your account.
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <x-text-input wire:model.live.debounce.300ms="search" type="text" class="mt-1 block w-full"
                        placeholder="Search domains..." />
                </div>

                <!-- Active Filter -->
                <div>
                    <select wire:model.live="filterActive"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <!-- Expiring Filter -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="filterExpiring"
                            class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Expiring Soon (30 days)</span>
                    </label>
                </div>
            </div>

            <!-- Tag Filter Row -->
            <div class="mt-4">
                <select wire:model.live="filterTag"
                    class="block w-full md:w-64 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Tags</option>
                    @foreach($this->availableTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Additional Filters Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Exclude Parked Filter -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="filterExcludeParked"
                            class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Exclude Parked Domains</span>
                    </label>
                </div>

                <!-- Recent Failures Filter -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="filterRecentFailures"
                            class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Recent Failures
                            ({{ config('domain_monitor.recent_failures_hours', 24) }} hours)</span>
                    </label>
                </div>

                <!-- Failed Eligibility Filter -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" wire:model.live="filterFailedEligibility"
                            class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Failed Eligibility Status</span>
                    </label>
                </div>
            </div>

            @if($search || $filterActive !== null || $filterExpiring || $filterExcludeParked || $filterRecentFailures || $filterFailedEligibility || $filterTag)
                <div class="mt-4">
                    <button wire:click="clearFilters" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Domains Table -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            @if($domains->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button type="button" wire:click="sortBy('domain')"
                                        class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        <span>Domain</span>
                                        @if($sortField === 'domain')
                                            <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button type="button" wire:click="sortBy('active')"
                                        class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        <span>Status</span>
                                        @if($sortField === 'active')
                                            <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button type="button" wire:click="sortBy('expires')"
                                        class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        <span>Expires</span>
                                        @if($sortField === 'expires')
                                            <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Issues
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($domains as $domain)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('domains.show', $domain->id) }}" wire:navigate
                                            class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                            {{ $domain->domain }}
                                        </a>
                                        @php
                                            $isParked = $domain->isParked();
                                            $isManuallyParked = (bool) $domain->parked_override;
                                        @endphp
                                        @if($isParked)
                                            <div class="mt-2">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Parked{{ $isManuallyParked ? ' (manual)' : '' }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($domain->project_key)
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $domain->project_key }}
                                            </div>
                                        @endif
                                        @if($domain->tags && $domain->tags->count() > 0)
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach($domain->tags->sortByDesc('priority') as $tag)
                                                    <span class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full"
                                                        style="background-color: {{ $tag->color }}20; color: {{ $tag->color }}; border: 1px solid {{ $tag->color }}40;"
                                                        title="{{ $tag->description ?? $tag->name }}">
                                                        {{ $tag->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-1">
                                            @if($domain->is_active)
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                            @else
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                            @endif
                                            @php
                                                // Get the latest HTTP check only (not DNS)
                                                $latestHttpCheck = $domain->checks->where('check_type', 'http')->first();
                                                $healthStatus = $latestHttpCheck ? $latestHttpCheck->status : null;
                                            @endphp
                                            @if($isParked)
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Parked
                                                </span>
                                            @elseif($healthStatus)
                                                @if($healthStatus === 'ok')
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">✓
                                                        Healthy</span>
                                                @elseif($healthStatus === 'warn')
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">⚠
                                                        Warning</span>
                                                @else
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">✗
                                                        Failed</span>
                                                @endif
                                            @else
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">No
                                                    Checks</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if($domain->expires_at)
                                            <div>{{ $domain->expires_at->format('Y-m-d') }}</div>
                                            @if($domain->expires_at->isPast())
                                                <div class="text-xs text-red-600 dark:text-red-400">Expired</div>
                                            @elseif($domain->expires_at->diffInDays(now()) <= 30)
                                                <div class="text-xs text-yellow-600 dark:text-yellow-400">
                                                    {{ $domain->expires_at->diffForHumans() }}</div>
                                            @else
                                                <div class="text-xs text-gray-400">{{ $domain->expires_at->diffForHumans() }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @php
                                            $failedSsl = $domain->latestSslCheck?->status === 'fail';
                                            $failedDmarc = $domain->latestEmailSecurityCheck?->status === 'fail';
                                            $failedDns = $domain->latestDnsCheck?->status === 'fail';
                                            $failedSeo = $domain->latestSeoCheck?->status === 'fail';
                                            $failedHeaders = $domain->latestSecurityHeadersCheck?->status === 'fail';
                                            
                                            $hasChecks = $domain->latestHttpCheck || $domain->latestSslCheck || $domain->latestEmailSecurityCheck || $domain->latestDnsCheck;
                                            $hasIssues = $failedSsl || $failedDmarc || $failedDns || $failedSeo || $failedHeaders;
                                        @endphp

                                        <div class="flex flex-wrap gap-1">
                                            @if(!$hasChecks)
                                                <span class="text-xs text-gray-400">Pending</span>
                                            @elseif(!$hasIssues)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                    OK
                                                </span>
                                            @else
                                                @if($failedSsl)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">SSL</span>
                                                @endif
                                                @if($failedDmarc)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">DMARC</span>
                                                @endif
                                                @if($failedDns)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">DNS</span>
                                                @endif
                                                @if($failedSeo)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">SEO</span>
                                                @endif
                                                @if($failedHeaders)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Security</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('domains.show', $domain->id) }}" wire:navigate
                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-3">View</a>
                                        <a href="{{ route('domains.edit', $domain->id) }}" wire:navigate
                                            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $domains->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400 mb-4">No domains found.</p>
                    <a href="{{ route('domains.create') }}" wire:navigate
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Add Your First Domain
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
</div>
</div>
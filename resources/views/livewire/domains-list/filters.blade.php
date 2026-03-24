<!-- Filters -->
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <div class="flex gap-2">
                    <x-text-input wire:model.live.debounce.300ms="search" type="text" class="mt-1 block w-full flex-1"
                        placeholder="Search domains or DNS records..." />
                    <select wire:model.live="searchMode"
                        class="mt-1 block rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                        title="Search mode">
                        <option value="all">All</option>
                        <option value="domain">Domain Only</option>
                        <option value="dns">DNS Only</option>
                    </select>
                </div>
            </div>

            <div>
                <select wire:model.live="filterActive"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <div>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="filterExpiring"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Expiring Soon (30 days)</span>
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <select wire:model.live="filterTag"
                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Tags</option>
                    @foreach($this->availableTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($searchMode === 'dns' || $searchMode === 'all')
                <div class="flex gap-2 flex-wrap items-end">
                    <div class="min-w-[120px]">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">DNS Type</label>
                        <select wire:model.live="dnsType"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">All Types</option>
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                            <option value="NS">NS</option>
                            <option value="SRV">SRV</option>
                            <option value="CAA">CAA</option>
                            <option value="SPF">SPF</option>
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Host/Subdomain</label>
                        <x-text-input wire:model.live.debounce.300ms="dnsHost" type="text"
                            class="block w-full text-sm"
                            placeholder="e.g. @, www, mail" />
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="dnsMissing"
                                class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                                Missing Records
                            </span>
                        </label>
                    </div>
                </div>
                @if($dnsMissing)
                    <div class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Showing domains <strong>without</strong> matching DNS records
                    </div>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="filterExcludeParked"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Exclude Parked Domains</span>
                </label>
            </div>

            <div>
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="filterRecentFailures"
                        class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Recent Failures
                        ({{ config('domain_monitor.recent_failures_hours', 24) }} hours)</span>
                </label>
            </div>

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

<div>
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Search -->
                            <div class="md:col-span-2">
                                <x-text-input wire:model.live.debounce.300ms="search" type="text" class="mt-1 block w-full" placeholder="Search domains..." />
                            </div>

                            <!-- Active Filter -->
                            <div>
                                <select wire:model.live="filterActive" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>

                            <!-- Expiring Filter -->
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.live="filterExpiring" class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Expiring Soon</span>
                                </label>
                            </div>
                        </div>

                        @if($search || $filterActive !== null || $filterExpiring)
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
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hosting</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($domains as $domain)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="{{ route('domains.show', $domain->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                                        {{ $domain->domain }}
                                                    </a>
                                                    @if($domain->project_key)
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $domain->project_key }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex flex-col gap-1">
                                                        @if($domain->is_active)
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                                        @else
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                                        @endif
                                                        @php
                                                            $latestCheck = $domain->checks->first();
                                                            $healthStatus = $latestCheck ? $latestCheck->status : null;
                                                        @endphp
                                                        @if($healthStatus)
                                                            @if($healthStatus === 'ok')
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">✓ Healthy</span>
                                                            @elseif($healthStatus === 'warn')
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">⚠ Warning</span>
                                                            @else
                                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">✗ Failed</span>
                                                            @endif
                                                        @else
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">No Checks</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    @if($domain->expires_at)
                                                        <div>{{ $domain->expires_at->format('Y-m-d') }}</div>
                                                        @if($domain->expires_at->isPast())
                                                            <div class="text-xs text-red-600 dark:text-red-400">Expired</div>
                                                        @elseif($domain->expires_at->diffInDays(now()) <= 30)
                                                            <div class="text-xs text-yellow-600 dark:text-yellow-400">{{ $domain->expires_at->diffForHumans() }}</div>
                                                        @else
                                                            <div class="text-xs text-gray-400">{{ $domain->expires_at->diffForHumans() }}</div>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $domain->platform?->platform_type ?? ($domain->platform ?? 'N/A') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $domain->hosting_provider ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="{{ route('domains.show', $domain->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-3">View</a>
                                                    <a href="{{ route('domains.edit', $domain->id) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Edit</a>
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
                                <a href="{{ route('domains.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                    Add Your First Domain
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
</div>

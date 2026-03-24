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

    @include('livewire.domains-list.platform-actions')

    @include('livewire.domains-list.sync-actions')

    @include('livewire.domains-list.filters')

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
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    SSL
                                </th>
                                <th
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    Email
                                </th>
                                <th
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    SEO
                                </th>
                                <th
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    Rep
                                </th>
                                <th
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    Sec
                                </th>
                                <th
                                    class="px-2 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                                    Link
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
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_ssl_status === 'fail')
                                            <span class="text-red-500" title="SSL Issue">❌</span>
                                        @elseif($domain->latest_ssl_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_ssl_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="Valid">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_email_security_status === 'fail')
                                            <span class="text-red-500" title="Email Security Issue">❌</span>
                                        @elseif($domain->latest_email_security_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_email_security_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="Valid">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_seo_status === 'fail')
                                            <span class="text-red-500" title="SEO Issue">❌</span>
                                        @elseif($domain->latest_seo_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_seo_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="Valid">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_reputation_status === 'fail')
                                            <span class="text-red-500" title="Reputation Issue">❌</span>
                                        @elseif($domain->latest_reputation_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_reputation_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="Clean">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_security_headers_status === 'fail')
                                            <span class="text-red-500" title="Security Headers Issue">❌</span>
                                        @elseif($domain->latest_security_headers_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_security_headers_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="Valid">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
                                        @if($domain->latest_broken_links_status === 'fail')
                                            <span class="text-red-500" title="Broken Links Found">❌</span>
                                        @elseif($domain->latest_broken_links_status === 'warn')
                                            <span class="text-orange-500" title="Warning">⚠️</span>
                                        @elseif(in_array($domain->latest_broken_links_status, ['pass', 'ok']))
                                            <span class="text-green-500" title="No Broken Links">✓</span>
                                        @else
                                            <span class="text-gray-300 text-xs">-</span>
                                        @endif
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

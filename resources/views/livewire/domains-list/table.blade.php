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
                            @include('livewire.domains-list.domain-row')
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $domains->links() }}
            </div>
        @else
            @include('livewire.domains-list.empty-state')
        @endif
    </div>
</div>

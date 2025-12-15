<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Settings</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage your application settings and tools</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Artisan Commands Card -->
        <a href="{{ route('settings.commands') }}" wire:navigate class="block">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-indigo-100 dark:bg-indigo-900 rounded-lg p-3">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Artisan Commands</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View all available Artisan commands</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>

        <!-- Scheduled Tasks Card -->
        <a href="{{ route('settings.scheduled-tasks') }}" wire:navigate class="block">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-lg p-3">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scheduled Tasks</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View all scheduled tasks and their next run times</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>

        <!-- Horizon Queue Dashboard Card -->
        <a href="/horizon" target="_blank" class="block">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-lg p-3">
                            <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Queue Dashboard</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor queue jobs and workers with Horizon</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>

        <!-- Domain Tags Card -->
        <a href="{{ route('settings.tags') }}" wire:navigate class="block">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition-shadow duration-200 cursor-pointer">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 rounded-lg p-3">
                            <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Domain Tags</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage tags to categorize and prioritize domains</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>

        <!-- Synergy Balance Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-lg p-3">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Synergy Balance</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Account credit balance</p>
                        </div>
                    </div>
                    <button 
                        wire:click="loadSynergyBalance" 
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="loadSynergyBalance">Refresh</span>
                        <span wire:loading wire:target="loadSynergyBalance">Loading...</span>
                    </button>
                </div>
                
                @if($loadingBalance)
                    <div class="text-center py-4">
                        <svg class="animate-spin h-5 w-5 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                @elseif($synergyBalance !== null)
                    <div class="text-center">
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($synergyBalance, 2) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Available credit</p>
                    </div>
                @elseif($synergyBalanceError)
                    <div class="text-center">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $synergyBalanceError }}</p>
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Click Refresh to check balance</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Placeholder for future cards -->
        <!-- Add more cards here as needed -->
    </div>
</div>

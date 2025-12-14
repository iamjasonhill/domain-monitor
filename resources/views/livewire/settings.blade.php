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

        <!-- Placeholder for future cards -->
        <!-- Add more cards here as needed -->
    </div>
</div>

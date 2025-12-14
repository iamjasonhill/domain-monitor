<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Artisan Commands</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">All available Artisan commands in the application</p>
        </div>
        <a href="{{ route('settings.index') }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
            ← Back to Settings
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            @if(count($commands) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Command</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signature</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($commands as $command)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <code class="text-sm font-mono text-indigo-600 dark:text-indigo-400">{{ $command['name'] }}</code>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $command['description'] ?: 'No description' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <code class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $command['signature'] }}</code>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Total: {{ count($commands) }} commands
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No commands found.</p>
            @endif
        </div>
    </div>
</div>

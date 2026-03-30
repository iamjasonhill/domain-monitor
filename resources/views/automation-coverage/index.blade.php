<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Automation Coverage
            </h2>
            <a href="{{ route('manual-csv-backlog.index') }}" wire:navigate class="inline-flex items-center rounded-md border border-yellow-300 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 px-3 py-2 text-sm font-medium text-yellow-900 dark:text-yellow-200 hover:bg-yellow-100 dark:hover:bg-yellow-900/30">
                Open Manual CSV Backlog
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:automation-coverage-queue />
        </div>
    </div>
</x-app-layout>

<!-- Header Actions -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="{{ route('domains.index') }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
            ← Back to Domains
        </a>
    </div>
    <div class="flex gap-4">
        <a href="{{ route('domains.edit', $domain->id) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
            Edit Domain
        </a>
        @if($isAustralianTld)
            <button wire:click="syncFromSynergy" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="syncFromSynergy">Sync Domain Info</span>
                <span wire:loading wire:target="syncFromSynergy">Syncing...</span>
            </button>
        @endif
        <button 
            wire:click="confirmDelete" 
            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
            Delete Domain
        </button>
    </div>
</div>

@if($syncMessage)
    <div class="mb-6 p-4 bg-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-100 dark:bg-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-900 border border-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-400 text-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-700 dark:text-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-300 rounded">
        {{ $syncMessage }}
    </div>
@endif

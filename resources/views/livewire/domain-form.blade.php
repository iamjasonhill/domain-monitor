<div>
    <div class="mb-6">
        <a href="{{ $domainId ? route('domains.show', $domainId) : route('domains.index') }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
            ← {{ $domainId ? 'Back to Domain' : 'Back to Domains' }}
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                {{ $domainId ? 'Edit Domain' : 'Add New Domain' }}
            </h3>

            <form wire:submit="save">
                <!-- Domain Name -->
                <div class="mb-4">
                    <x-input-label for="domain_name" value="Domain Name *" />
                    <x-text-input wire:model="domain_name" id="domain_name" type="text" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('domain_name')" class="mt-2" />
                </div>

                <!-- Project Key -->
                <div class="mb-4">
                    <x-input-label for="project_key" value="Project Key" />
                    <x-text-input wire:model="project_key" id="project_key" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('project_key')" class="mt-2" />
                </div>

                <!-- Registrar -->
                <div class="mb-4">
                    <x-input-label for="registrar" value="Registrar" />
                    <x-text-input wire:model="registrar" id="registrar" type="text" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('registrar')" class="mt-2" />
                </div>

                <!-- Hosting Provider -->
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <x-input-label for="hosting_provider" value="Hosting Provider" />
                        @if($domainId)
                            <button type="button" wire:click="detectHosting" wire:loading.attr="disabled" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                <span wire:loading.remove wire:target="detectHosting">Auto-detect</span>
                                <span wire:loading wire:target="detectHosting">Detecting...</span>
                            </button>
                        @endif
                    </div>
                    <x-text-input wire:model="hosting_provider" id="hosting_provider" type="text" class="mt-1 block w-full" placeholder="Vercel, Render, Cloudflare, AWS, Netlify, Other" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Manually enter hosting provider or use auto-detect</p>
                    <x-input-error :messages="$errors->get('hosting_provider')" class="mt-2" />
                </div>

                <!-- Hosting Admin URL -->
                <div class="mb-4">
                    <x-input-label for="hosting_admin_url" value="Hosting Admin URL" />
                    <x-text-input wire:model="hosting_admin_url" id="hosting_admin_url" type="url" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('hosting_admin_url')" class="mt-2" />
                </div>

                <!-- Platform -->
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <x-input-label for="platform" value="Platform" />
                        @if($domainId)
                            <button type="button" wire:click="detectPlatform" wire:loading.attr="disabled" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                <span wire:loading.remove wire:target="detectPlatform">Auto-detect</span>
                                <span wire:loading wire:target="detectPlatform">Detecting...</span>
                            </button>
                        @endif
                    </div>
                    <x-text-input wire:model="platform" id="platform" type="text" class="mt-1 block w-full" placeholder="WordPress, Laravel, Next.js, Shopify, Static, Other" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Manually enter platform or use auto-detect</p>
                    <x-input-error :messages="$errors->get('platform')" class="mt-2" />
                </div>

                <!-- Check Frequency -->
                <div class="mb-4">
                    <x-input-label for="check_frequency_minutes" value="Check Frequency (minutes)" />
                    <x-text-input wire:model="check_frequency_minutes" id="check_frequency_minutes" type="number" min="1" max="10080" class="mt-1 block w-full" required />
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">How often to run health checks (1-10080 minutes, max 1 week)</p>
                    <x-input-error :messages="$errors->get('check_frequency_minutes')" class="mt-2" />
                </div>

                <!-- Is Active -->
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Active</span>
                    </label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                </div>

                <!-- Notes -->
                <div class="mb-6">
                    <x-input-label for="notes" value="Notes" />
                    <textarea wire:model="notes" id="notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"></textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-4">
                    <a href="{{ $domainId ? route('domains.show', $domainId) : route('domains.index') }}" wire:navigate class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Cancel
                    </a>
                    <x-primary-button wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $domainId ? 'Update' : 'Create' }} Domain</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DNS Record Add/Edit Modal -->
<x-modal name="dns-record" wire:model="showDnsRecordModal" close-action="closeDnsRecordModal" focusable wire:key="dns-record-modal">
    <form wire:submit="saveDnsRecord" class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ $editingDnsRecordId ? 'Edit DNS Record' : 'Add DNS Record' }}
        </h2>

        @if (session()->has('error'))
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 rounded">
                <p class="text-sm font-medium">{{ session('error') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 rounded">
                <p class="text-sm font-medium mb-1">Please fix the following errors:</p>
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <x-input-label for="dns_record_host" value="Host/Subdomain" />
                <x-text-input wire:model="dnsRecordHost" id="dns_record_host" type="text" class="mt-1 block w-full" placeholder="e.g., www, mail, or leave empty/@ for root" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty or use @ for root domain. Enter subdomain name (e.g., www, mail) for subdomain records.</p>
                <x-input-error :messages="$errors->get('dnsRecordHost')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="dns_record_type" value="Type" />
                <select wire:model="dnsRecordType" id="dns_record_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-xs">
                    <option value="A">A (IPv4 Address)</option>
                    <option value="AAAA">AAAA (IPv6 Address)</option>
                    <option value="CNAME">CNAME (Canonical Name)</option>
                    <option value="MX">MX (Mail Exchange)</option>
                    <option value="NS">NS (Name Server)</option>
                    <option value="TXT">TXT (Text Record)</option>
                    <option value="SRV">SRV (Service Record)</option>
                    <option value="CAA">CAA (Certificate Authority Authorization)</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use CAA only when you intentionally manage certificate authorities. Format example: <code>0 issue "letsencrypt.org"</code></p>
                <x-input-error :messages="$errors->get('dnsRecordType')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="dns_record_value" value="Value" />
                <x-text-input wire:model="dnsRecordValue" id="dns_record_value" type="text" class="mt-1 block w-full" placeholder="e.g., 192.0.2.1 or example.com" required />
                @if($dnsRecordType === 'CAA')
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CAA records must include a numeric flag, tag, and quoted value. Example: <code>0 issue "letsencrypt.org"</code></p>
                @endif
                <x-input-error :messages="$errors->get('dnsRecordValue')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="dns_record_ttl" value="TTL (seconds)" />
                <x-text-input wire:model="dnsRecordTtl" id="dns_record_ttl" type="number" min="60" max="86400" class="mt-1 block w-full" required />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Common values: 300 (5 min), 3600 (1 hour), 86400 (1 day)</p>
                <x-input-error :messages="$errors->get('dnsRecordTtl')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="dns_record_priority" value="Priority" />
                <x-text-input wire:model="dnsRecordPriority" id="dns_record_priority" type="number" min="0" max="65535" class="mt-1 block w-full" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Required for MX records (typically 10-100)</p>
                <x-input-error :messages="$errors->get('dnsRecordPriority')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <x-secondary-button type="button" wire:click="closeDnsRecordModal" wire:loading.attr="disabled">
                Cancel
            </x-secondary-button>
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveDnsRecord">
                <span wire:loading.remove wire:target="saveDnsRecord">
                    {{ $editingDnsRecordId ? 'Update' : 'Add' }} Record
                </span>
                <span wire:loading wire:target="saveDnsRecord">
                    <span class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </span>
            </x-primary-button>
        </div>
    </form>
</x-modal>

<!-- Subdomain Add/Edit Modal -->
<x-modal name="subdomain" wire:model="showSubdomainModal" close-action="closeSubdomainModal" focusable wire:key="subdomain-modal">
    <form wire:submit="saveSubdomain" class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ $editingSubdomainId ? 'Edit Subdomain' : 'Add Subdomain' }}
        </h2>

        <div class="space-y-4">
            <div>
                <x-input-label for="subdomain_name" value="Subdomain Name" />
                <x-text-input wire:model="subdomainName" id="subdomain_name" type="text" class="mt-1 block w-full" placeholder="www, api, blog, etc." />
                <x-input-error :messages="$errors->get('subdomainName')" class="mt-2" />
                @if($domain && $subdomainName)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Full domain: <span class="font-mono">{{ $subdomainName }}.{{ $domain->domain }}</span>
                    </p>
                @endif
            </div>

            <div>
                <x-input-label for="subdomain_notes" value="Notes (optional)" />
                <textarea wire:model="subdomainNotes" id="subdomain_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-xs" placeholder="Optional notes about this subdomain..."></textarea>
                <x-input-error :messages="$errors->get('subdomainNotes')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-4">
            <x-secondary-button wire:click="closeSubdomainModal" type="button">
                Cancel
            </x-secondary-button>
            <x-primary-button type="submit">
                {{ $editingSubdomainId ? 'Update' : 'Add' }} Subdomain
            </x-primary-button>
        </div>
    </form>
</x-modal>

<!-- Delete Confirmation Modal -->
<x-modal name="delete-domain" wire:model="showDeleteModal" close-action="closeDeleteModal" focusable>
    <form wire:submit="deleteDomain" class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Are you sure you want to delete this domain?') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Once this domain is deleted, all of its health checks and alerts will be permanently removed. This action cannot be undone.') }}
        </p>

        @if($domain)
            <p class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                Domain: <span class="font-bold">{{ $domain->domain }}</span>
            </p>
        @endif

        <div class="mt-6 flex justify-end gap-4">
            <x-secondary-button wire:click="closeDeleteModal" type="button">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button type="submit">
                {{ __('Delete Domain') }}
            </x-danger-button>
        </div>
    </form>
</x-modal>

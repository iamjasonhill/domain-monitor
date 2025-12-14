<div>
    @if($domain)
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
                @if(str_ends_with($domain->domain, '.com.au'))
                    <button wire:click="syncFromSynergy" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="syncFromSynergy">Sync from Synergy</span>
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

        <!-- Parked Domain Alert -->
        @if($domain->platform === 'Parked')
            <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 text-yellow-800 dark:text-yellow-200 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold">This domain is parked</h4>
                        <p class="text-sm mt-1">This domain appears to be parked and is not actively being used for a website.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Domain Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Basic Information -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Basic Information</h3>
                    <dl class="grid grid-cols-1 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ $domain->domain }}</dd>
                        </div>
                        @if($domain->project_key)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Project Key</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->project_key }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1">
                                @if($domain->is_active)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                @endif
                            </dd>
                        </div>
                        @if($domain->registrar)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrar</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registrar }}</dd>
                            </div>
                        @endif
                        @if($domain->expires_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $domain->expires_at->format('Y-m-d H:i:s') }}
                                    @if($domain->expires_at->isPast())
                                        <span class="text-red-600 dark:text-red-400">(Expired)</span>
                                    @elseif($domain->expires_at->diffInDays(now()) <= 30)
                                        <span class="text-yellow-600 dark:text-yellow-400">({{ $domain->expires_at->diffForHumans() }})</span>
                                    @else
                                        <span class="text-gray-500">({{ $domain->expires_at->diffForHumans() }})</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if($domain->created_at_synergy)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created (Synergy)</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->created_at_synergy->format('Y-m-d') }}</dd>
                            </div>
                        @endif
                        @if($domain->auto_renew !== null)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Auto-Renew</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->auto_renew ? 'Yes' : 'No' }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Platform & Hosting -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Platform & Hosting</h3>
                        <div class="flex gap-2">
                            <button wire:click="detectPlatform" wire:loading.attr="disabled" class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="detectPlatform">Detect Platform</span>
                                <span wire:loading wire:target="detectPlatform">Detecting...</span>
                            </button>
                            <button wire:click="detectHosting" wire:loading.attr="disabled" class="inline-flex items-center px-3 py-1.5 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="detectHosting">Detect Hosting</span>
                                <span wire:loading wire:target="detectHosting">Detecting...</span>
                            </button>
                        </div>
                    </div>
                    <dl class="grid grid-cols-1 gap-4">
                        @php
                            // Get the relationship model (if loaded) and the string attribute separately
                            $platformModel = $domain->relationLoaded('platform') ? $domain->getRelation('platform') : null;
                            $platformString = $domain->getAttribute('platform'); // This is the string field
                            
                            // If we have a relationship model, use it; otherwise use the string
                            $platform = $platformModel instanceof \App\Models\WebsitePlatform ? $platformModel : null;
                        @endphp
                        @if($platform || $platformString)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Platform</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $platform?->platform_type ?? $platformString }}
                                    @if($platform?->platform_version)
                                        <span class="text-gray-500">({{ $platform->platform_version }})</span>
                                    @endif
                                    @if($platform?->detection_confidence)
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($platform->detection_confidence === 'high') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($platform->detection_confidence === 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ ucfirst($platform->detection_confidence) }} confidence
                                        </span>
                                    @endif
                                    @if($platform?->admin_url)
                                        <div class="mt-1">
                                            <a href="{{ $platform->admin_url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">Admin URL →</a>
                                        </div>
                                    @endif
                                    @if($platform?->last_detected)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Last detected: {{ $platform->last_detected->diffForHumans() }}
                                        </div>
                                    @endif
                                </dd>
                            </div>
                        @else
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Platform</dt>
                                <dd class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Not detected yet. Click "Detect Platform" to run detection.</dd>
                            </div>
                        @endif
                        @if($domain->hosting_provider)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Hosting Provider</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $domain->hosting_provider }}
                                    @if($domain->hosting_admin_url)
                                        <div class="mt-1">
                                            <a href="{{ $domain->hosting_admin_url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">Admin URL →</a>
                                        </div>
                                    @endif
                                </dd>
                            </div>
                        @else
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Hosting Provider</dt>
                                <dd class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Not detected yet. Click "Detect Hosting" to run detection.</dd>
                            </div>
                        @endif
                        @if($domain->dns_config_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">DNS Config</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->dns_config_name }}</dd>
                            </div>
                        @endif
                        @if($domain->nameservers && count($domain->nameservers) > 0)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nameservers</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <ul class="list-disc list-inside">
                                        @foreach($domain->nameservers as $nameserver)
                                            <li>{{ $nameserver }}</li>
                                        @endforeach
                                    </ul>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Synergy Wholesale Information (for .com.au domains) -->
        @if(str_ends_with($domain->domain, '.com.au') && ($domain->registrant_name || $domain->eligibility_type))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Australian Domain Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($domain->registrant_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrant</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registrant_name }}</dd>
                            </div>
                        @endif
                        @if($domain->registrant_id_type && $domain->registrant_id)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrant ID</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registrant_id_type }}: {{ $domain->registrant_id }}</dd>
                            </div>
                        @endif
                        @if($domain->eligibility_type)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Eligibility Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->eligibility_type }}</dd>
                            </div>
                        @endif
                        @if($domain->eligibility_valid !== null)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Eligibility Status</dt>
                                <dd class="mt-1">
                                    @if($domain->eligibility_valid)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Valid</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Invalid</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if($domain->eligibility_last_check)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Eligibility Check</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->eligibility_last_check->format('Y-m-d') }}</dd>
                            </div>
                        @endif
                        @if($domain->domain_status)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain Status</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->domain_status }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        @endif

        <!-- Health Check Actions -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Run Health Checks</h3>
                <div class="flex flex-wrap gap-4">
                    <button wire:click="runHealthCheck('http')" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('http')">HTTP Check</span>
                        <span wire:loading wire:target="runHealthCheck('http')">Running...</span>
                    </button>
                    <button wire:click="runHealthCheck('ssl')" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('ssl')">SSL Check</span>
                        <span wire:loading wire:target="runHealthCheck('ssl')">Running...</span>
                    </button>
                    <button wire:click="runHealthCheck('dns')" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('dns')">DNS Check</span>
                        <span wire:loading wire:target="runHealthCheck('dns')">Running...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Health Checks -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Health Checks</h3>
                @if($domain->checks->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Response Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($domain->checks as $check)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 uppercase">
                                            {{ $check->check_type }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($check->status === 'ok')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">OK</span>
                                            @elseif($check->status === 'warn')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Warn</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Fail</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $check->response_code ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $check->duration_ms ? $check->duration_ms.'ms' : 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $check->created_at->diffForHumans() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">No health checks yet. Run a check using the buttons above.</p>
                @endif
            </div>
        </div>

        <!-- DNS Records -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">DNS Records</h3>
                    @if(str_ends_with($domain->domain, '.com.au'))
                        <div class="flex gap-2">
                            <button wire:click="openAddDnsRecordModal" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                                Add Record
                            </button>
                            <button wire:click="syncDnsRecords" wire:loading.attr="disabled" class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="syncDnsRecords">Sync</span>
                                <span wire:loading wire:target="syncDnsRecords">Syncing...</span>
                            </button>
                        </div>
                    @endif
                </div>
                @if($domain->dnsRecords && $domain->dnsRecords->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Host</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Value</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">TTL</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                    @if(str_ends_with($domain->domain, '.com.au'))
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($domain->dnsRecords as $record)
                                    <tr>
                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100 font-mono break-words max-w-[200px]">
                                            {{ $record->host }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                @if($record->type === 'A') bg-blue-100 text-blue-800 dark:bg-blue-500 dark:text-white
                                                @elseif($record->type === 'AAAA') bg-cyan-100 text-cyan-800 dark:bg-cyan-500 dark:text-white
                                                @elseif($record->type === 'CNAME') bg-green-100 text-green-800 dark:bg-green-500 dark:text-white
                                                @elseif($record->type === 'MX') bg-purple-100 text-purple-800 dark:bg-purple-500 dark:text-white
                                                @elseif($record->type === 'NS') bg-indigo-100 text-indigo-800 dark:bg-indigo-500 dark:text-white
                                                @elseif($record->type === 'SOA') bg-pink-100 text-pink-800 dark:bg-pink-500 dark:text-white
                                                @elseif($record->type === 'TXT') bg-yellow-100 text-yellow-800 dark:bg-yellow-500 dark:text-gray-900
                                                @elseif($record->type === 'SRV') bg-orange-100 text-orange-800 dark:bg-orange-500 dark:text-white
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                                                @endif">
                                                {{ $record->type }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100 font-mono break-words max-w-[300px]">
                                            <div class="break-all">{{ $record->value }}</div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                            {{ $record->ttl ?? 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                            {{ $record->priority ?? 'N/A' }}
                                        </td>
                                        @if(str_ends_with($domain->domain, '.com.au'))
                                            <td class="px-3 py-2 whitespace-nowrap text-xs">
                                                <div class="flex gap-1">
                                                    <button wire:click="openEditDnsRecordModal('{{ $record->id }}')" class="px-2 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-xs">
                                                        Edit
                                                    </button>
                                                    <button wire:click="deleteDnsRecord('{{ $record->id }}')" wire:confirm="Are you sure you want to delete this DNS record?" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($domain->dnsRecords->first()?->synced_at)
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            Last synced: {{ $domain->dnsRecords->first()->synced_at->diffForHumans() }}
                        </p>
                    @endif
                @else
                    <p class="text-gray-500 dark:text-gray-400">
                        @if(str_ends_with($domain->domain, '.com.au'))
                            No DNS records synced yet. Click "Sync DNS Records" to retrieve them from Synergy Wholesale.
                        @else
                            DNS records are only available for .com.au domains via Synergy Wholesale.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-center">
                <p class="text-gray-500 dark:text-gray-400">Domain not found.</p>
                <a href="{{ route('domains.index') }}" wire:navigate class="mt-4 inline-block text-blue-600 dark:text-blue-400 hover:underline">
                    Back to Domains
                </a>
            </div>
        </div>
    @endif

    <!-- DNS Record Add/Edit Modal -->
    @if($showDnsRecordModal)
        <div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" style="display: block;">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 opacity-75" wire:click="closeDnsRecordModal"></div>

            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-2xl sm:mx-auto relative">
                <form wire:submit="saveDnsRecord" class="p-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editingDnsRecordId ? 'Edit DNS Record' : 'Add DNS Record' }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Host -->
                        <div>
                            <x-input-label for="dns_record_host" value="Host/Subdomain" />
                            <x-text-input wire:model="dnsRecordHost" id="dns_record_host" type="text" class="mt-1 block w-full" placeholder="e.g., www, mail, @ for root" required />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty or use @ for root domain</p>
                            <x-input-error :messages="$errors->get('dnsRecordHost')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div>
                            <x-input-label for="dns_record_type" value="Type" />
                            <select wire:model="dnsRecordType" id="dns_record_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                                <option value="A">A (IPv4 Address)</option>
                                <option value="AAAA">AAAA (IPv6 Address)</option>
                                <option value="CNAME">CNAME (Canonical Name)</option>
                                <option value="MX">MX (Mail Exchange)</option>
                                <option value="NS">NS (Name Server)</option>
                                <option value="TXT">TXT (Text Record)</option>
                                <option value="SRV">SRV (Service Record)</option>
                            </select>
                            <x-input-error :messages="$errors->get('dnsRecordType')" class="mt-2" />
                        </div>

                        <!-- Value -->
                        <div>
                            <x-input-label for="dns_record_value" value="Value" />
                            <x-text-input wire:model="dnsRecordValue" id="dns_record_value" type="text" class="mt-1 block w-full" placeholder="e.g., 192.0.2.1 or example.com" required />
                            <x-input-error :messages="$errors->get('dnsRecordValue')" class="mt-2" />
                        </div>

                        <!-- TTL -->
                        <div>
                            <x-input-label for="dns_record_ttl" value="TTL (seconds)" />
                            <x-text-input wire:model="dnsRecordTtl" id="dns_record_ttl" type="number" min="60" max="86400" class="mt-1 block w-full" required />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Common values: 300 (5 min), 3600 (1 hour), 86400 (1 day)</p>
                            <x-input-error :messages="$errors->get('dnsRecordTtl')" class="mt-2" />
                        </div>

                        <!-- Priority (for MX records) -->
                        <div>
                            <x-input-label for="dns_record_priority" value="Priority" />
                            <x-text-input wire:model="dnsRecordPriority" id="dns_record_priority" type="number" min="0" max="65535" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Required for MX records (typically 10-100)</p>
                            <x-input-error :messages="$errors->get('dnsRecordPriority')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDnsRecordModal">
                            Cancel
                        </x-secondary-button>
                        <x-primary-button type="submit">
                            {{ $editingDnsRecordId ? 'Update' : 'Add' }} Record
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    <x-modal name="delete-domain" :show="$showDeleteModal" focusable>
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
</div>

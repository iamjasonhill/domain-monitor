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
                @php
                    $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
                @endphp
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

        @php
            $isParked = $domain->isParked();
            $isManuallyParked = (bool) $domain->parked_override;
        @endphp

        <!-- Parked Domain Alert -->
        @if($isParked)
            <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 text-yellow-800 dark:text-yellow-200 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold">This domain is parked</h4>
                        <p class="text-sm mt-1">
                            @if($isManuallyParked)
                                This domain has been manually marked as parked. Health checks are disabled.
                            @else
                                This domain appears to be parked (detected). Health checks are disabled.
                            @endif
                        </p>
                        @if($isManuallyParked && $domain->parked_override_set_at)
                            <p class="text-xs mt-1 opacity-75">
                                Marked parked {{ $domain->parked_override_set_at->diffForHumans() }}.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Email Only Domain Alert -->
        @if($domain->platform === 'Email Only')
            <div class="mb-6 p-4 bg-blue-100 dark:bg-blue-900 border border-blue-400 text-blue-800 dark:text-blue-200 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                    </svg>
                    <div>
                        <h4 class="font-semibold">Email Only Domain</h4>
                        <p class="text-sm mt-1">This domain is configured for email only and does not have web hosting (no A records).</p>
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
                            <dd class="mt-2 flex gap-3">
                                <a href="https://{{ $domain->domain }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Open HTTPS
                                </a>
                                <a href="http://{{ $domain->domain }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 hover:underline">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Open HTTP
                                </a>
                            </dd>
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
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->created_at_synergy->format('Y-m-d') }}</dd>
                            </div>
                        @endif
                        @if($domain->auto_renew !== null)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Auto-Renew</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->auto_renew ? 'Yes' : 'No' }}</dd>
                            </div>
                        @endif
                        @if($domain->renewed_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Renewed</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $domain->renewed_at->format('Y-m-d H:i:s') }}
                                    <span class="text-gray-500">({{ $domain->renewed_at->diffForHumans() }})</span>
                                    @if($domain->renewed_by)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            via {{ $domain->renewed_by === 'auto-renew' ? 'Auto-Renew System' : ucfirst($domain->renewed_by) }}
                                        </div>
                                    @endif
                                </dd>
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
                            <button
                                wire:click="toggleParkedOverride"
                                class="inline-flex items-center px-3 py-1.5 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700">
                                {{ $domain->parked_override ? 'Unmark Parked' : 'Mark Parked' }}
                            </button>
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
                                    @php
                                        $platformType = $platform?->platform_type ?? $platformString;
                                    @endphp
                                    @if($platformType === 'Parked')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Parked</span>
                                    @elseif($platformType === 'Email Only')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Email Only</span>
                                    @else
                                        {{ $platformType }}
                                    @endif
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
                                    @php
                                        $adminUrl = $domain->hosting_admin_url;
                                        if (!$adminUrl && $domain->hosting_provider) {
                                            $adminUrl = \App\Services\HostingProviderUrls::getLoginUrl($domain->hosting_provider);
                                        }
                                    @endphp
                                    @if($adminUrl)
                                        <div class="mt-1">
                                            <a href="{{ $adminUrl }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                                @if($domain->hosting_admin_url)
                                                    Admin URL →
                                                @else
                                                    Suggested Login →
                                                @endif
                                            </a>
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

        <!-- Domain Information (for Australian TLD domains) -->
        @php
            $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
        @endphp
        @if($isAustralianTld && ($domain->registrant_name || $domain->eligibility_type))
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
                @if($isParked)
                    <div class="mb-4 text-sm text-yellow-800 dark:text-yellow-200 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 rounded p-3">
                        Health checks are disabled because this domain is marked as parked.
                    </div>
                @endif
                <div class="flex flex-wrap gap-4">
                    <button wire:click="runHealthCheck('http')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('http')">HTTP Check</span>
                        <span wire:loading wire:target="runHealthCheck('http')">Running...</span>
                    </button>
                    <button wire:click="runHealthCheck('ssl')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('ssl')">SSL Check</span>
                        <span wire:loading wire:target="runHealthCheck('ssl')">Running...</span>
                    </button>
                    <button wire:click="runHealthCheck('dns')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50">
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

        <!-- Subdomains -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Subdomains</h3>
                    <div class="flex gap-2">
                        @if($domain->dnsRecords && $domain->dnsRecords->count() > 0)
                            <button wire:click="discoverSubdomainsFromDns" wire:loading.attr="disabled" class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="discoverSubdomainsFromDns">Discover from DNS</span>
                                <span wire:loading wire:target="discoverSubdomainsFromDns">Discovering...</span>
                            </button>
                        @endif
                        @if($domain->subdomains && $domain->subdomains->count() > 0)
                            <button wire:click="updateAllSubdomainsIp" wire:loading.attr="disabled" class="inline-flex items-center px-3 py-1.5 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="updateAllSubdomainsIp">Update All IPs</span>
                                <span wire:loading wire:target="updateAllSubdomainsIp">Updating...</span>
                            </button>
                        @endif
                        <button wire:click="openAddSubdomainModal" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                            Add Subdomain
                        </button>
                    </div>
                </div>
                @if($domain->subdomains && $domain->subdomains->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subdomain</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">IP Address</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hosting Provider</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Organization</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Checked</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($domain->subdomains as $subdomain)
                                    <tr>
                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100 font-mono">
                                            {{ $subdomain->full_domain }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100 font-mono">
                                            {{ $subdomain->ip_address ?? 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100">
                                            <div>
                                                {{ $subdomain->hosting_provider ?? ($subdomain->ip_organization ?? 'N/A') }}
                                                @php
                                                    $subdomainProvider = $subdomain->hosting_provider ?? $subdomain->ip_organization;
                                                    $subdomainAdminUrl = $subdomain->hosting_admin_url;
                                                    if (!$subdomainAdminUrl && $subdomainProvider) {
                                                        $subdomainAdminUrl = \App\Services\HostingProviderUrls::getLoginUrl($subdomainProvider);
                                                    }
                                                @endphp
                                                @if($subdomainAdminUrl)
                                                    <div class="mt-1">
                                                        <a href="{{ $subdomainAdminUrl }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                                            @if($subdomain->hosting_admin_url)
                                                                Login →
                                                            @else
                                                                Suggested Login →
                                                            @endif
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $subdomain->ip_organization ?? 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $subdomain->ip_checked_at ? $subdomain->ip_checked_at->diffForHumans() : 'Never' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-xs">
                                            <div class="flex gap-1">
                                                <button wire:click="updateSubdomainIp('{{ $subdomain->id }}')" class="px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">
                                                    Update IP
                                                </button>
                                                <button wire:click="openEditSubdomainModal('{{ $subdomain->id }}')" class="px-2 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-xs">
                                                    Edit
                                                </button>
                                                <button wire:click="deleteSubdomain('{{ $subdomain->id }}')" wire:confirm="Are you sure you want to delete this subdomain?" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">No subdomains added yet. Click "Add Subdomain" to add one.</p>
                @endif
            </div>
        </div>

        <!-- DNS Records -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">DNS Records</h3>
                    @php
                        $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
                    @endphp
                    @if($isAustralianTld)
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
                                    @php
                                        $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
                                    @endphp
                                    @if($isAustralianTld)
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
                                        @if($isAustralianTld)
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
                        @php
                            $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
                        @endphp
                        @if($isAustralianTld)
                            No DNS records synced yet. Click "Sync DNS Records" to retrieve them.
                        @else
                            DNS records are only available for Australian TLD domains (.com.au, .net.au, etc.).
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <!-- Email Security (SPF/DMARC) -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email Security</h3>
                    <button wire:click="runHealthCheck('email_security')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('email_security')">Run Security Check</span>
                        <span wire:loading wire:target="runHealthCheck('email_security')">Checking...</span>
                    </button>
                </div>

                @php
                    $latestSecurityCheck = $domain->checks()->where('check_type', 'email_security')->latest()->first();
                    $payload = $latestSecurityCheck?->payload ?? [];
                    $spf = $payload['spf'] ?? null;
                    $dmarc = $payload['dmarc'] ?? null;
                @endphp

                @if($latestSecurityCheck)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- SPF Status -->
                        <div class="border rounded-md p-4 {{ $spf && $spf['valid'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold {{ $spf && $spf['valid'] ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' }}">SPF</h4>
                                @if($spf && $spf['valid'])
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">PASS</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100">FAIL</span>
                                @endif
                            </div>
                            
                            @if($spf)
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Present:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $spf['present'] ? 'Yes' : 'No' }}</span>
                                    </div>
                                    @if($spf['record'])
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                            <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $spf['record'] }}</code>
                                        </div>
                                    @endif
                                    @if($spf['mechanism'])
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Mechanism:</span>
                                            <span class="font-mono text-gray-900 dark:text-gray-100">{{ $spf['mechanism'] }}</span>
                                        </div>
                                    @endif
                                    @if($spf['error'])
                                        <div class="text-red-600 dark:text-red-400 text-xs mt-2">
                                            Error: {{ $spf['error'] }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No data available.</p>
                            @endif
                        </div>

                        <!-- DMARC Status -->
                        <div class="border rounded-md p-4 {{ $dmarc && $dmarc['valid'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold {{ $dmarc && $dmarc['valid'] ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' }}">DMARC</h4>
                                @if($dmarc && $dmarc['valid'])
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">PASS</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100">FAIL</span>
                                @endif
                            </div>

                            @if($dmarc)
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Present:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $dmarc['present'] ? 'Yes' : 'No' }}</span>
                                    </div>
                                    @if($dmarc['record'])
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                            <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $dmarc['record'] }}</code>
                                        </div>
                                    @endif
                                    @if($dmarc['policy'])
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Policy:</span>
                                            <span class="font-mono text-gray-900 dark:text-gray-100">{{ $dmarc['policy'] }}</span>
                                        </div>
                                    @endif
                                    @if($dmarc['error'])
                                        <div class="text-red-600 dark:text-red-400 text-xs mt-2">
                                            Error: {{ $dmarc['error'] }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No data available.</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                        Last checked: {{ $latestSecurityCheck->created_at->diffForHumans() }} ({{ $latestSecurityCheck->duration_ms }}ms)
                    </div>
                @else
                    <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                        <p>No security check data available yet.</p>
                        <p class="text-sm mt-2">Click "Run Security Check" to analyze SPF and DMARC.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Deployments -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Deployments</h3>
                @if($domain->deployments && $domain->deployments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Deployed At</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commit</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($domain->deployments->take(10) as $deployment)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $deployment->deployed_at->format('Y-m-d H:i') }}
                                            <span class="text-gray-500 dark:text-gray-400 text-xs">({{ $deployment->deployed_at->diffForHumans() }})</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-gray-100">
                                            @if($deployment->git_commit)
                                                <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">{{ substr($deployment->git_commit, 0, 7) }}</code>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                            {{ $deployment->notes ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($domain->deployments->count() > 10)
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Showing 10 most recent of {{ $domain->deployments->count() }} total deployments.</p>
                    @endif
                @else
                    <p class="text-gray-500 dark:text-gray-400">No deployments recorded yet. Add deployment tracking to your CI/CD pipeline to see history here.</p>
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
                        <!-- Host -->
                        <div>
                            <x-input-label for="dns_record_host" value="Host/Subdomain" />
                            <x-text-input wire:model="dnsRecordHost" id="dns_record_host" type="text" class="mt-1 block w-full" placeholder="e.g., www, mail, or leave empty/@ for root" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty or use @ for root domain. Enter subdomain name (e.g., www, mail) for subdomain records.</p>
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
            </div>
        </div>
    @endif

    <!-- Subdomain Add/Edit Modal -->
    @if($showSubdomainModal)
        <div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" style="display: block;">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 opacity-75" wire:click="closeSubdomainModal"></div>

            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-2xl sm:mx-auto relative">
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
                            <textarea wire:model="subdomainNotes" id="subdomain_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Optional notes about this subdomain..."></textarea>
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

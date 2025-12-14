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
            </div>
        </div>

        @if($syncMessage)
            <div class="mb-6 p-4 bg-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-100 dark:bg-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-900 border border-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-400 text-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-700 dark:text-{{ str_contains($syncMessage, 'Error') ? 'red' : 'green' }}-300 rounded">
                {{ $syncMessage }}
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
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Platform & Hosting</h3>
                    <dl class="grid grid-cols-1 gap-4">
                        @if($domain->platform)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Platform</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->platform }}</dd>
                            </div>
                        @elseif($domain->platform)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Platform</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $domain->platform->platform_type }}
                                    @if($domain->platform->platform_version)
                                        <span class="text-gray-500">({{ $domain->platform->platform_version }})</span>
                                    @endif
                                    @if($domain->platform->admin_url)
                                        <div class="mt-1">
                                            <a href="{{ $domain->platform->admin_url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">Admin URL →</a>
                                        </div>
                                    @endif
                                </dd>
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
</div>

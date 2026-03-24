<div>
    @if($domain)
        @php
            $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
            $isParked = $domain->isParked();
            $isManuallyParked = (bool) $domain->parked_override;
        @endphp

        @include('livewire.domain-detail.sections.header-actions')
        @include('livewire.domain-detail.sections.status-alerts')
        @include('livewire.domain-detail.sections.overview-panels')
        @include('livewire.domain-detail.sections.australian-domain-info')
        @include('livewire.domain-detail.sections.contact-information')

        <!-- Active Alerts -->
        @php
            $activeAlerts = $domain->alerts()->whereNull('resolved_at')->orderByDesc('triggered_at')->get();
        @endphp
        @if($activeAlerts->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Active Alerts</h3>
                    <div class="space-y-3">
                        @foreach($activeAlerts as $alert)
                            <div class="border-l-4 @if($alert->severity === 'critical' || $alert->severity === 'error') border-red-500 @elseif($alert->severity === 'warning') border-yellow-500 @else border-blue-500 @endif bg-@if($alert->severity === 'critical' || $alert->severity === 'error') red-50 dark:bg-red-900/20 @elseif($alert->severity === 'warning') yellow-50 dark:bg-yellow-900/20 @else blue-50 dark:bg-blue-900/20 @endif p-4 rounded-r">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="px-2 py-1 text-xs font-semibold rounded @if($alert->severity === 'critical' || $alert->severity === 'error') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @elseif($alert->severity === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @else bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @endif uppercase">
                                                {{ $alert->severity }}
                                            </span>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                @if($alert->alert_type === 'compliance_issue')
                                                    Compliance Issue
                                                @elseif($alert->alert_type === 'renewal_required')
                                                    Renewal Required
                                                @elseif($alert->alert_type === 'domain_expiring')
                                                    Domain Expiring
                                                @elseif($alert->alert_type === 'ssl_expiring')
                                                    SSL Expiring
                                                @else
                                                    {{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }}
                                                @endif
                                            </span>
                                        </div>
                                        @if($alert->payload)
                                            @php
                                                $payload = $alert->payload;
                                            @endphp
                                            <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                @if(isset($payload['reason']))
                                                    <p><strong>Reason:</strong> {{ $payload['reason'] }}</p>
                                                @endif
                                                @if(isset($payload['days_until_expiry']))
                                                    <p><strong>Days until expiry:</strong> {{ $payload['days_until_expiry'] }}</p>
                                                @endif
                                                @if(isset($payload['expires_at']))
                                                    <p><strong>Expires at:</strong> {{ \Carbon\Carbon::parse($payload['expires_at'])->format('Y-m-d H:i:s') }}</p>
                                                @endif
                                            </div>
                                        @endif
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                            Triggered {{ $alert->triggered_at->diffForHumans() }} ({{ $alert->triggered_at->format('Y-m-d H:i:s') }})
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Compliance Check History -->
        @php
            $complianceChecks = $domain->complianceChecks()->orderByDesc('checked_at')->limit(10)->get();
        @endphp
        @if($isAustralianTld && $complianceChecks->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Compliance Check History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Source</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($complianceChecks as $check)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $check->checked_at->format('Y-m-d H:i:s') }}
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $check->checked_at->diffForHumans() }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($check->is_compliant)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Compliant</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Non-Compliant</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            @if($check->compliance_reason)
                                                <span class="text-red-600 dark:text-red-400">{{ $check->compliance_reason }}</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ ucfirst($check->source) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
                    <button wire:click="runHealthCheck('security_headers')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="runHealthCheck('security_headers')">Security Headers Check</span>
                        <span wire:loading wire:target="runHealthCheck('security_headers')">Running...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Health Checks -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2 cursor-pointer" wire:click="$toggle('showHealthChecks')">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Health Checks</h3>
                        <svg class="w-5 h-5 text-gray-500 transform transition-transform {{ $showHealthChecks ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    @if(!$showHealthChecks)
                        <button wire:click="$toggle('showHealthChecks')" class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600">
                            Show ({{ $this->recentChecks->count() }})
                        </button>
                    @endif
                </div>
                @if($showHealthChecks)
                    @if($this->recentChecks->count() > 0)
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
                                @foreach($this->recentChecks as $check)
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
                @endif
            </div>
        </div>

        <!-- Subdomains -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2 cursor-pointer" wire:click="$toggle('showSubdomains')">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Subdomains</h3>
                        <svg class="w-5 h-5 text-gray-500 transform transition-transform {{ $showSubdomains ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="flex gap-2">
                        @if($showSubdomains)
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
                        @else
                            <button wire:click="$toggle('showSubdomains')" class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600">
                                Show ({{ $domain->subdomains->count() }})
                            </button>
                        @endif
                    </div>
                </div>
                @if($showSubdomains)
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
                @endif
            </div>
        </div>

        <!-- Uptime & Performance -->
        @php
            $latestUptimeCheck = $domain->checks()->where('check_type', 'uptime')->latest()->first();
            $uptimePayload = $latestUptimeCheck?->payload ?? [];
        @endphp
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                 <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Uptime & Performance
                    </h3>
                    @if($latestUptimeCheck)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $latestUptimeCheck->status === 'ok' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                            {{ $latestUptimeCheck->status === 'ok' ? 'Online' : 'Offline' }}
                        </span>
                    @endif
                 </div>

                 @if($latestUptimeCheck)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Response Time</div>
                            <div class="mt-1 flex items-baseline">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $latestUptimeCheck->duration_ms }}</span>
                                <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">ms</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Checked</div>
                            <div class="mt-1 flex items-baseline">
                                <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $latestUptimeCheck->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    @if($this->uptimeIncidents->count() > 0)
                        <div class="mt-6">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wider">Uptime History (Last 10)</h4>
                            <div class="overflow-hidden border border-gray-100 dark:border-gray-700 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Started</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ended</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($this->uptimeIncidents as $incident)
                                            <tr class="text-xs">
                                                <td class="px-3 py-2 whitespace-nowrap text-gray-900 dark:text-gray-100">
                                                    {{ $incident->started_at->format('M j, H:i:s') }}
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    {{ $incident->ended_at ? $incident->ended_at->format('M j, H:i:s') : 'Ongoing' }}
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    {{ $incident->ended_at ? $incident->started_at->diffForHumans($incident->ended_at, true) : '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-red-600 dark:text-red-400">
                                                    {{ $incident->status_code ?? 'Error' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                 @else
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                        No uptime data available yet.
                    </div>
                 @endif
            </div>
        </div>

        <!-- SSL/TLS Configuration -->
        @php
            $latestSslCheck = $domain->checks()
                ->where('check_type', 'ssl')
                ->latest('started_at')
                ->first();
            $sslPayload = $latestSslCheck ? $latestSslCheck->payload : null;
            $chain = $latestSslCheck ? ($latestSslCheck->chain ?? ($sslPayload['chain'] ?? [])) : [];
            $protocol = $latestSslCheck->protocol ?? ($sslPayload['protocol'] ?? null);
            $cipher = $latestSslCheck->cipher ?? ($sslPayload['cipher'] ?? null);
        @endphp
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    SSL/TLS Configuration
                </h3>

                            @if($latestSslCheck)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Protocol & Cipher -->
                                    <div class="space-y-4">
                                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Connection Security</h4>
                                            
                                            <div class="mb-3">
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Protocol</div>
                                                <div class="font-mono text-sm dark:text-gray-200">
                                                    @if($protocol)
                                                        @if(in_array($protocol, ['TLSv1.2', 'TLSv1.3']))
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                {{ $protocol }}
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                                {{ $protocol }} (Obsolete)
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400 italic">Unknown</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cipher Suite</div>
                                                <div class="font-mono text-xs dark:text-gray-200 break-all">
                                                    {{ $cipher ?? 'Unknown' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Certificate Chain -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Certificate Chain</h4>
                                        @if(!empty($chain))
                                            <div class="space-y-2 relative pl-2">
                                                <!-- Visual line connecting chain items -->
                                                <div class="absolute left-4 top-2 bottom-4 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

                                                @foreach($chain as $index => $cert)
                                                    <div class="relative flex items-start pl-6">
                                                        <!-- Dot visual -->
                                                        <div class="absolute left-3 top-2.5 w-2.5 h-2.5 rounded-full {{ $index === 0 ? 'bg-indigo-500' : ($index === count($chain) - 1 ? 'bg-indigo-300' : 'bg-indigo-400') }} ring-4 ring-white dark:ring-gray-800"></div>
                                                        
                                                        <div class="flex-1 min-w-0 bg-gray-50 dark:bg-gray-700/50 rounded p-2 text-xs border border-gray-100 dark:border-gray-700">
                                                            <div class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                                                {{ $cert['subject'] }}
                                                            </div>
                                                            <div class="text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                                                Issued by: {{ $cert['issuer'] }}
                                                            </div>
                                                            @if(isset($cert['valid_to']))
                                                                <div class="text-gray-400 dark:text-gray-500 mt-1 flex items-center gap-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                                    Expires: {{ $cert['valid_to'] }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                                                Chain information not available. Run a new SSL check to capture full details.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-right">
                                    Last checked: {{ $latestSslCheck->created_at->diffForHumans() }}
                                </div>
                            @else
                                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                                    <p>No detailed SSL data available.</p>
                                    <p class="text-sm mt-2">Run an SSL check to analyze connection security.</p>
                                </div>
                            @endif
                        </div>
                    </div>

        <!-- DNS Records -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2 cursor-pointer" wire:click="$toggle('showDnsRecords')">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">DNS Records</h3>
                        <svg class="w-5 h-5 text-gray-500 transform transition-transform {{ $showDnsRecords ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    @php
                        $isAustralianTld = preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain->domain);
                    @endphp

                    @if($showDnsRecords)

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
                    @else
                        <button wire:click="$toggle('showDnsRecords')" class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600">
                            Show ({{ $domain->dnsRecords->count() }})
                        </button>
                    @endif
                </div>
                @if($showDnsRecords)
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
                    $dnssec = $payload['dnssec'] ?? null;
                    $caa = $payload['caa'] ?? null;
                    $dkim = $payload['dkim'] ?? null;
                    
                    $helpers = [
                        'spf' => 'Specifies which mail servers are authorized to send email on behalf of your domain.',
                        'dmarc' => 'Tells email receivers how to handle mail that isn\'t authenticated using SPF or DKIM.',
                        'dkim' => 'Adds a cryptographic signature to emails, verifying they were sent by you and weren\'t altered.',
                        'dnssec' => 'Protects your DNS records from being tampered with (spoofing).',
                        'caa' => 'Limits which Certificate Authorities can issue SSL certificates for your domain.'
                    ];
                @endphp

                @if($latestSecurityCheck)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- SPF Status -->
                        <div class="border rounded-md p-4 {{ $spf && $spf['valid'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold flex items-center {{ $spf && $spf['valid'] ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' }}">
                                    SPF
                                    <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                        <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full"
                                             x-cloak>
                                            {{ $helpers['spf'] }}
                                        </div>
                                    </div>
                                </h4>
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
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($spf['present'] ?? false) ? 'Yes' : 'No' }}</span>
                                    </div>
                                    @if($spf['record'])
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                            <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $spf['record'] }}</code>
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

                            @if(!$spf || !$spf['valid'])
                                <div class="mt-3 pt-3 border-t border-red-200 dark:border-red-900/50">
                                    <h5 class="text-xs font-semibold text-red-800 dark:text-red-300 mb-1">How to Fix</h5>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        @if(!$spf || !($spf['present'] ?? false))
                                            Add this TXT record to your domain (@) to allow sending emails.
                                        @else
                                            Your SPF record is invalid. Consider resetting it to this safe default.
                                        @endif
                                    </p>
                                    <div x-data="{ copied: false }" class="relative">
                                        <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                            <code class="text-xs text-gray-800 dark:text-gray-200 font-mono break-all select-all">v=spf1 a mx ~all</code>
                                            <button @click="navigator.clipboard.writeText('v=spf1 a mx ~all'); copied = true; setTimeout(() => copied = false, 2000)" 
                                                    class="ml-2 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 focus:outline-none"
                                                    title="Copy to clipboard">
                                                <span x-show="!copied"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg></span>
                                                <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></span>
                                            </button>
                                        </div>
                                        <p class="mt-1 text-[10px] text-gray-500">Note: Add <code>include:service.com</code> if you use external email providers (e.g. Mailgun, SendGrid).</p>
                                    </div>
                                    <div class="mt-2 text-right">
                                        <button 
                                            wire:click="applyFix('spf')"
                                            wire:confirm="This will modify your domain's live DNS records. Are you sure you want to proceed?"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900/50 dark:text-indigo-300 dark:hover:bg-indigo-900 transition-colors duration-200">
                                            <svg wire:loading.remove wire:target="applyFix('spf')" class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            <svg wire:loading wire:target="applyFix('spf')" class="animate-spin -ml-1 mr-2 h-3 w-3 text-indigo-700 dark:text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Apply Fix Automatically
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- DMARC Status -->
                        <div class="border rounded-md p-4 {{ $dmarc && $dmarc['valid'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold flex items-center {{ $dmarc && $dmarc['valid'] ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' }}">
                                    DMARC
                                    <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                        <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full"
                                             x-cloak>
                                            {{ $helpers['dmarc'] }}
                                        </div>
                                    </div>
                                </h4>
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
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dmarc['present'] ?? false) ? 'Yes' : 'No' }}</span>
                                    </div>
                                    @if($dmarc['record'])
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                            <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $dmarc['record'] }}</code>
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

                            @if(!$dmarc || !$dmarc['valid'])
                                <div class="mt-3 pt-3 border-t border-red-200 dark:border-red-900/50">
                                    <h5 class="text-xs font-semibold text-red-800 dark:text-red-300 mb-1">How to Fix</h5>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        @if(!$dmarc || !($dmarc['present'] ?? false))
                                            Add this TXT record to <code>_dmarc</code> subdomain.
                                        @else
                                            Your DMARC record is invalid. Use this standard policy.
                                        @endif
                                    </p>
                                    <div x-data="{ copied: false }" class="relative">
                                        <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                            <code class="text-xs text-gray-800 dark:text-gray-200 font-mono break-all select-all">v=DMARC1; p=none;</code>
                                            <button @click="navigator.clipboard.writeText('v=DMARC1; p=none;'); copied = true; setTimeout(() => copied = false, 2000)" 
                                                    class="ml-2 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 focus:outline-none"
                                                    title="Copy to clipboard">
                                                <span x-show="!copied"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg></span>
                                                <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-right">
                                        <button 
                                            wire:click="applyFix('dmarc')"
                                            wire:confirm="This will modify your domain's live DNS records. Are you sure you want to proceed?"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900/50 dark:text-indigo-300 dark:hover:bg-indigo-900 transition-colors duration-200">
                                            <svg wire:loading.remove wire:target="applyFix('dmarc')" class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            <svg wire:loading wire:target="applyFix('dmarc')" class="animate-spin -ml-1 mr-2 h-3 w-3 text-indigo-700 dark:text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Apply Fix Automatically
                                        </button>
                                    </div>
                                </div>

                        <!-- DKIM Status -->
                        <div class="border rounded-md p-4 {{ $dkim && ($dkim['present'] ?? false) ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold flex items-center {{ $dkim && ($dkim['present'] ?? false) ? 'text-green-800 dark:text-green-300' : 'text-yellow-800 dark:text-yellow-300' }}">
                                    DKIM
                                    <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                        <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full"
                                             x-cloak>
                                            {{ $helpers['dkim'] }}
                                        </div>
                                    </div>
                                </h4>
                                @if($dkim && ($dkim['present'] ?? false))
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">PASS</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-yellow-200 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">NOT FOUND</span>
                                @endif
                            </div>
                            
                            @if($dkim)
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Selectors Found:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dkim['present'] ?? false) ? count($dkim['selectors']) : 'None' }}</span>
                                    </div>
                                    @if(($dkim['present'] ?? false) && !empty($dkim['selectors']))
                                        <div class="mt-2 text-xs">
                                            @foreach($dkim['selectors'] as $selector)
                                                <div class="mb-2">
                                                    <span class="font-mono font-bold text-gray-700 dark:text-gray-300">{{ $selector['selector'] }}</span>
                                                    <code class="block w-full mt-1 p-1.5 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-100 dark:border-gray-800 break-all">{{ $selector['record'] }}</code>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif(!($dkim['present'] ?? false))
                                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                            <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">How to Fix</h5>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                                DKIM cannot be fixed automatically because it requires a private key from your email provider.
                                            </p>
                                            <ol class="list-decimal list-inside text-xs text-gray-600 dark:text-gray-400 space-y-1 ml-1">
                                                <li>Log in to your email provider's admin panel (e.g., Google Workspace, Office 365, Zoho).</li>
                                                <li>Look for "DKIM" or "Email Authentication" settings.</li>
                                                <li>Generate a new DKIM key. They will give you a <strong>Selector</strong> and a <strong>TXT Record</strong>.</li>
                                                <li>Add that TXT record to your DNS records.</li>
                                                <li>Enter the <strong>Selector</strong> name in the "Custom DKIM Selectors" box below and click Save.</li>
                                            </ol>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <label for="dkim_selectors" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Custom DKIM Selectors (comma separated)
                                    </label>
                                    <div class="flex gap-2">
                                        <input 
                                            type="text" 
                                            id="dkim_selectors" 
                                            wire:model="dkimSelectorsInput" 
                                            placeholder="e.g. key1, selector1"
                                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                        <button 
                                            wire:click="saveDkimSelectors" 
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center px-2 py-1 bg-gray-600 border border-transparent rounded-md font-semibold text-[10px] text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="saveDkimSelectors">Save</span>
                                            <span wire:loading wire:target="saveDkimSelectors">...</span>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-[10px] text-gray-500">
                                        Add selectors used by your email providers to help discovery.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- DNSSEC Status -->
                        <div class="border rounded-md p-4 {{ $dnssec && $dnssec['enabled'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-gray-200 bg-gray-50 dark:bg-gray-900/20 dark:border-gray-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold flex items-center {{ $dnssec && $dnssec['enabled'] ? 'text-green-800 dark:text-green-300' : 'text-gray-800 dark:text-gray-300' }}">
                                    DNSSEC
                                    <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                        <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full"
                                             x-cloak>
                                            {{ $helpers['dnssec'] }}
                                        </div>
                                    </div>
                                </h4>
                                @if($dnssec && $dnssec['enabled'])
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">SECURE</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-100">NOT SECURE</span>
                                @endif
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Enabled:</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $dnssec && $dnssec['enabled'] ? 'Yes' : 'No' }}</span>
                                </div>
                                @if($dnssec && $dnssec['error'])
                                    <div class="text-red-600 dark:text-red-400 text-xs mt-2">
                                        Error: {{ $dnssec['error'] }}
                                    </div>
                                @endif
                            </div>
                            
                            @if(!$dnssec || !$dnssec['enabled'])
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">How to Fix</h5>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        Log in to your <strong>domain registrar's dashboard</strong> (e.g., GoDaddy, Namecheap) and look for a "DNSSEC" or "Domain Security" setting. Enable it there—no manual record creation is usually required.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- CAA Status -->
                        <div class="border rounded-md p-4 {{ $caa && ($caa['present'] ?? false) ? 'border-indigo-200 bg-indigo-50 dark:bg-indigo-900/20 dark:border-indigo-800' : 'border-gray-200 bg-gray-50 dark:bg-gray-900/20 dark:border-gray-800' }}">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold flex items-center {{ $caa && ($caa['present'] ?? false) ? 'text-indigo-800 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-300' }}">
                                    CAA
                                    <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                        <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full"
                                             x-cloak>
                                            {{ $helpers['caa'] }}
                                        </div>
                                    </div>
                                </h4>
                                @if($caa && ($caa['present'] ?? false))
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-indigo-200 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-100">PRESENT</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-100">MISSING</span>
                                @endif
                            </div>
                            
                            @if(!$caa || !($caa['present'] ?? false))
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">How to Fix</h5>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        Recommended: Allow Let's Encrypt to issue certificates.
                                    </p>
                                    <div x-data="{ copied: false }" class="relative">
                                        <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
                                            <code class="text-xs text-gray-800 dark:text-gray-200 font-mono break-all select-all">0 issue "letsencrypt.org"</code>
                                            <button @click="navigator.clipboard.writeText('0 issue &quot;letsencrypt.org&quot;'); copied = true; setTimeout(() => copied = false, 2000)" 
                                                    class="ml-2 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 focus:outline-none"
                                                    title="Copy to clipboard">
                                                <span x-show="!copied"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg></span>
                                                <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-right">
                                        <button 
                                            wire:click="applyFix('caa')"
                                            wire:confirm="This will modify your domain's live DNS records. Are you sure you want to proceed?"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-indigo-900/50 dark:text-indigo-300 dark:hover:bg-indigo-900 transition-colors duration-200">
                                            <svg wire:loading.remove wire:target="applyFix('caa')" class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            <svg wire:loading wire:target="applyFix('caa')" class="animate-spin -ml-1 mr-2 h-3 w-3 text-indigo-700 dark:text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Apply Fix Automatically
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Configured:</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $caa && ($caa['present'] ?? false) ? 'Yes' : 'No' }}</span>
                                </div>
                                @if($caa && ($caa['present'] ?? false) && !empty($caa['records']))
                                    <div class="mt-2">
                                        <span class="text-gray-600 dark:text-gray-400 block mb-1">Authorized CAs:</span>
                                        <div class="space-y-1">
                                            @foreach($caa['records'] as $record)
                                                <code class="block w-full text-xs p-1.5 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-100 dark:border-gray-800">{{ $record }}</code>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
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

        @include('livewire.domain-detail.sections.analysis-panels')
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

    @include('livewire.domain-detail.sections.modals')
    @endif
</div>

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
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resolution</th>
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
                                        <td class="px-3 py-2 text-xs">
                                            @php($category = $subdomain->category())
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                @if($category === 'web')
                                                    bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                                                @elseif($category === 'email_auth')
                                                    bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300
                                                @elseif($category === 'service')
                                                    bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300
                                                @else
                                                    bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200
                                                @endif">
                                                {{ $subdomain->categoryLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @if($subdomain->resolutionState() === 'unchecked')
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                                    {{ $subdomain->resolutionLabel() }}
                                                </span>
                                            @elseif($subdomain->resolutionState() === 'resolves')
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                                    {{ $subdomain->resolutionLabel() }}
                                                </span>
                                            @elseif($subdomain->resolutionState() === 'not_applicable')
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                                    {{ $subdomain->resolutionLabel() }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                    {{ $subdomain->resolutionLabel() }}
                                                </span>
                                            @endif
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
                        <p class="text-gray-500 dark:text-gray-400">No subdomains tracked yet. Use "Discover from DNS" to build the inventory from the live zone, or add one manually.</p>
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
                                                @elseif($record->type === 'CAA') bg-teal-100 text-teal-800 dark:bg-teal-500 dark:text-white
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

        @include('livewire.domain-detail.sections.email-security')

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

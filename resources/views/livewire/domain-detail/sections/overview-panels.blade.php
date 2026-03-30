<!-- Domain Information -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Basic Information -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
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
                @if($domain->tags && $domain->tags->count() > 0)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tags</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @foreach($domain->tags->sortByDesc('priority') as $tag)
                                <span class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full"
                                    style="background-color: {{ $tag->color }}20; color: {{ $tag->color }} border: 1px solid {{ $tag->color }}40;"
                                    title="{{ $tag->description ?? $tag->name }}">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </dd>
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
                @if($domain->transfer_lock !== null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Transfer Lock</dt>
                        <dd class="mt-1">
                            @if($domain->transfer_lock)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Locked</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Unlocked</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($domain->renewal_required !== null || $domain->can_renew !== null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Renewal Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            @if($domain->renewal_required)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Renewal Required</span>
                            @elseif($domain->can_renew !== null)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Can Renew</span>
                            @else
                                <span class="text-gray-500 dark:text-gray-400">Not Required</span>
                            @endif
                        </dd>
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
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
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
                    $platformModel = $domain->relationLoaded('platform') ? $domain->getRelation('platform') : null;
                    $platformString = $domain->getAttribute('platform');
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
                                    <a href="{{ $platform->admin_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">Admin URL →</a>
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
                                if (! $adminUrl && $domain->hosting_provider) {
                                    $adminUrl = \App\Services\HostingProviderUrls::getLoginUrl($domain->hosting_provider);
                                }
                            @endphp
                            @if($adminUrl)
                                <div class="mt-1">
                                    <a href="{{ $adminUrl }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
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
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {{ $domain->dns_config_name }}
                            @if($domain->dns_config_id)
                                <span class="text-gray-500 dark:text-gray-400">(ID: {{ $domain->dns_config_id }})</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($domain->id_protect)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID Protection</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->id_protect }}</dd>
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

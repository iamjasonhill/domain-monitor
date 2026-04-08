<div>
    @if($property)
        @php
            $health = $property->healthSummary();
            $domains = $property->orderedDomainLinks();
            $repositories = $property->repositories;
            $analyticsSources = $property->analyticsSources;
            $tags = collect($property->tagSummaries());
            $conversionLinks = $property->conversionLinkSummary();
            $canonicalOrigin = $property->canonicalOriginSummary();
            $automationCoverage = $property->automationCoverageSummary();
            $automationChecks = [
                [
                    'title' => 'Controller',
                    'summary' => $automationCoverage['checks']['repository'],
                    'queue' => route('automation-coverage.index'),
                ],
                [
                    'title' => 'Matomo',
                    'summary' => $automationCoverage['checks']['matomo'],
                    'queue' => route('matomo-coverage.index'),
                ],
                [
                    'title' => 'Search Console',
                    'summary' => $automationCoverage['checks']['search_console'],
                    'queue' => route('search-console-coverage.index'),
                ],
                [
                    'title' => 'Baseline Sync',
                    'summary' => $automationCoverage['checks']['baseline_sync'],
                    'queue' => route('automation-coverage.index'),
                ],
                [
                    'title' => 'Manual CSV',
                    'summary' => $automationCoverage['checks']['manual_csv'],
                    'queue' => route('manual-csv-backlog.index'),
                ],
            ];
        @endphp

        @if (session('message'))
            <div class="mb-6 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                {{ session('message') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-6">
            <a href="{{ route('web-properties.index') }}" wire:navigate class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to web properties
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $property->name }}</h3>
                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ $property->slug }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                {{ str_replace('_', ' ', ucfirst($property->property_type)) }}
                            </span>
                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ ucfirst($property->status) }}
                            </span>
                            @if($property->platform)
                                <span class="inline-flex items-center rounded-full bg-purple-50 dark:bg-purple-900/30 px-2.5 py-0.5 text-xs font-medium text-purple-700 dark:text-purple-300">
                                    {{ $property->platform }}
                                </span>
                            @endif
                        </div>
                        @if($property->notes)
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">{{ $property->notes }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 min-w-[260px]">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Domains</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $domains->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Repos</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $repositories->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Analytics</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $analyticsSources->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-4">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Alerts</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $health['active_alerts_count'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Property Summary</h4>
                    <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Primary Domain</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->primaryDomainName() ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Production URL</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100 break-all">{{ $property->production_url ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Staging URL</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100 break-all">{{ $property->staging_url ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Owner</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->owner ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Priority</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $property->priority ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Overall Health</dt>
                            <dd class="mt-1">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $health['overall_status'] === 'ok',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $health['overall_status'] === 'warn',
                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $health['overall_status'] === 'fail',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $health['overall_status'] === 'unknown',
                                ])>
                                    {{ strtoupper($health['overall_status']) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tags</h4>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if($tags->isEmpty())
                            <span class="text-sm text-gray-500 dark:text-gray-400">No tags linked through domains yet.</span>
                        @else
                            @foreach($tags as $tag)
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                    style="background-color: {{ $tag['color'] ?? '#6B7280' }}">
                                    {{ $tag['name'] }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div id="linked-domains" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Canonical Origin Policy</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            This defines the one public origin Fleet may normalize within for this property only. It does not authorize apex normalization across unrelated subdomains.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="saveCanonicalOriginPolicy"
                        wire:loading.attr="disabled"
                        wire:target="saveCanonicalOriginPolicy"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Save Canonical Policy
                    </button>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Current Contract</h5>
                        <div class="mt-4 space-y-4 text-sm">
                            @foreach([
                                'Scheme' => $canonicalOrigin['scheme'],
                                'Host' => $canonicalOrigin['host'],
                                'Base URL' => $canonicalOrigin['base_url'],
                                'Policy' => $canonicalOrigin['policy'],
                                'Scope' => $canonicalOrigin['scope'],
                                'Enforcement Eligible' => $canonicalOrigin['enforcement_eligible'] ? 'Yes' : 'No',
                                'Sitemap Policy Known' => $canonicalOrigin['sitemap_policy_known'] ? 'Yes' : 'No',
                            ] as $label => $value)
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                    <div class="mt-1 break-all font-medium text-gray-900 dark:text-gray-100">{{ $value ?: 'Not set' }}</div>
                                </div>
                            @endforeach

                            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Owned Subdomains</div>
                                @if(($canonicalOrigin['owned_subdomains'] ?? []) !== [])
                                    <div class="mt-2 space-y-1">
                                        @foreach($canonicalOrigin['owned_subdomains'] as $host)
                                            <div class="break-all text-gray-900 dark:text-gray-100">{{ $host }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-1 text-gray-500 dark:text-gray-400">None declared on this property</div>
                                @endif
                            </div>

                            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Excluded Subdomains</div>
                                @if(($canonicalOrigin['excluded_subdomains'] ?? []) !== [])
                                    <div class="mt-2 space-y-1">
                                        @foreach($canonicalOrigin['excluded_subdomains'] as $host)
                                            <div class="break-all text-gray-900 dark:text-gray-100">{{ $host }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-1 text-gray-500 dark:text-gray-400">No excluded subdomains configured</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Declared Policy</h5>

                        <div class="mt-4 grid grid-cols-1 gap-4">
                            <div>
                                <label for="canonical_origin_policy" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Policy</label>
                                <select id="canonical_origin_policy" wire:model="canonicalOriginPolicy" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="unknown">Unknown</option>
                                    <option value="known">Known</option>
                                </select>
                                @error('canonicalOriginPolicy') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="canonical_origin_scheme" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Scheme</label>
                                <select id="canonical_origin_scheme" wire:model="canonicalOriginScheme" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">Not set</option>
                                    <option value="https">https</option>
                                    <option value="http">http</option>
                                </select>
                                @error('canonicalOriginScheme') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="canonical_origin_host" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Host</label>
                                <input id="canonical_origin_host" type="text" wire:model="canonicalOriginHost" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="movingagain.com.au" />
                                @error('canonicalOriginHost') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="canonical_origin_excluded_subdomains" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Excluded Subdomains</label>
                                <textarea id="canonical_origin_excluded_subdomains" wire:model="canonicalOriginExcludedSubdomainsText" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="cartransport.movingagain.com.au&#10;quotes.movingagain.com.au"></textarea>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">One hostname per line. These stay outside canonical-origin normalization for this property.</div>
                                @error('canonicalOriginExcludedSubdomains') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                                @error('canonicalOriginExcludedSubdomains.*') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <label class="inline-flex items-start gap-3">
                                <input type="checkbox" wire:model="canonicalOriginEnforcementEligible" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900" />
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Canonical origin enforcement eligible</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Only enable when this property has a known canonical public origin and Fleet may safely normalize within it.</span>
                                </span>
                            </label>
                            @error('canonicalOriginEnforcementEligible') <div class="text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror

                            <label class="inline-flex items-start gap-3">
                                <input type="checkbox" wire:model="canonicalOriginSitemapPolicyKnown" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900" />
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Sitemap policy known</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Mark this once the property’s sitemap host policy is known well enough for automated normalization work.</span>
                                </span>
                            </label>
                            @error('canonicalOriginSitemapPolicyKnown') <div class="text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Conversion Links</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Current URLs are scanned from the live site navigation. Target URLs are the admin-maintained source of truth Fleet should migrate toward for quote, booking, and fixed destination links.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if($conversionLinks['scanned_at'])
                                Last scanned {{ \Illuminate\Support\Carbon::parse($conversionLinks['scanned_at'])->format('Y-m-d H:i') }}
                            @else
                                Not scanned yet
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="refreshCurrentConversionLinks"
                            wire:loading.attr="disabled"
                            wire:target="refreshCurrentConversionLinks"
                            class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                        >
                            Refresh Current Links
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Current Live Links</h5>
                        <div class="mt-4 space-y-4 text-sm">
                            @foreach([
                                'Household Quote' => $conversionLinks['current']['household_quote'],
                                'Household Booking' => $conversionLinks['current']['household_booking'],
                                'Vehicle Quote' => $conversionLinks['current']['vehicle_quote'],
                                'Vehicle Booking' => $conversionLinks['current']['vehicle_booking'],
                            ] as $label => $url)
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                    @if($url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block break-all font-medium text-blue-600 hover:underline dark:text-blue-400">
                                            {{ $url }}
                                        </a>
                                    @else
                                        <div class="mt-1 text-gray-500 dark:text-gray-400">Not detected</div>
                                    @endif
                                </div>
                            @endforeach

                            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Legacy Moveroo Endpoint Drift</div>
                                <div class="mt-3 space-y-4">
                                    @foreach([
                                        'Legacy /bookings' => $conversionLinks['legacy_endpoints']['legacy_booking_endpoint'] ?? null,
                                        'Legacy /payments' => $conversionLinks['legacy_endpoints']['legacy_payment_endpoint'] ?? null,
                                    ] as $label => $endpoint)
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                            @if($endpoint)
                                                <div class="mt-1 break-all text-gray-900 dark:text-gray-100">{{ $endpoint['url'] }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Found on {{ $endpoint['found_on'] ?? 'n/a' }}
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Resolves to {{ $endpoint['resolved_url'] ?? 'unknown' }} @if(isset($endpoint['resolved_status'])) (HTTP {{ $endpoint['resolved_status'] }}) @endif
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Host changed: {{ array_key_exists('resolved_host_changed', $endpoint) ? ($endpoint['resolved_host_changed'] ? 'Yes' : 'No') : 'Unknown' }}
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Preferred replacement: {{ $endpoint['preferred_replacement'] ?? 'Manual review' }}
                                                </div>
                                            @else
                                                <div class="mt-1 text-gray-500 dark:text-gray-400">Not detected</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Target Links</h5>
                            <button
                                type="button"
                                wire:click="saveConversionTargets"
                                wire:loading.attr="disabled"
                                wire:target="saveConversionTargets"
                                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Save Targets
                            </button>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4">
                            <div>
                                <label for="target_household_quote_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Household Quote</label>
                                <input id="target_household_quote_url" type="url" wire:model="targetHouseholdQuoteUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetHouseholdQuoteUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_household_booking_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Household Booking</label>
                                <input id="target_household_booking_url" type="url" wire:model="targetHouseholdBookingUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetHouseholdBookingUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_vehicle_quote_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Vehicle Quote</label>
                                <input id="target_vehicle_quote_url" type="url" wire:model="targetVehicleQuoteUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetVehicleQuoteUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_vehicle_booking_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Vehicle Booking</label>
                                <input id="target_vehicle_booking_url" type="url" wire:model="targetVehicleBookingUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetVehicleBookingUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_moveroo_subdomain_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Moveroo Subdomain</label>
                                <input id="target_moveroo_subdomain_url" type="url" wire:model="targetMoverooSubdomainUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetMoverooSubdomainUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_contact_us_page_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact Us Page</label>
                                <input id="target_contact_us_page_url" type="url" wire:model="targetContactUsPageUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetContactUsPageUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_legacy_bookings_replacement_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Legacy /bookings Replacement</label>
                                <input id="target_legacy_bookings_replacement_url" type="url" wire:model="targetLegacyBookingsReplacementUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetLegacyBookingsReplacementUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="target_legacy_payments_replacement_url" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Legacy /payments Replacement</label>
                                <input id="target_legacy_payments_replacement_url" type="url" wire:model="targetLegacyPaymentsReplacementUrl" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="https://..." />
                                @error('targetLegacyPaymentsReplacementUrl') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Automation Checklist</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            This shows what is automated for this property already and what still needs operator attention.
                        </p>
                    </div>
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $automationCoverage['status'] === 'complete',
                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($automationCoverage['status'], ['manual_csv_pending', 'needs_baseline_sync', 'import_stale', 'needs_onboarding'], true),
                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => in_array($automationCoverage['status'], ['needs_controller', 'needs_matomo_binding', 'needs_search_console_mapping'], true),
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($automationCoverage['status'], ['complete', 'manual_csv_pending', 'needs_baseline_sync', 'import_stale', 'needs_onboarding', 'needs_controller', 'needs_matomo_binding', 'needs_search_console_mapping'], true),
                    ])>
                        {{ $automationCoverage['label'] }}
                    </span>
                </div>

                <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($automationChecks as $check)
                        @php
                            $summary = $check['summary'];
                        @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $check['title'] }}</div>
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $summary['status'] === 'covered',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($summary['status'], ['pending', 'needs_sync', 'stale', 'needs_import', 'stale_import', 'bound_unverified', 'blocked'], true),
                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => in_array($summary['status'], ['needs_repository', 'needs_binding', 'bound_attention', 'needs_matomo', 'needs_property', 'url_prefix_only'], true),
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($summary['status'], ['covered', 'pending', 'needs_sync', 'stale', 'needs_import', 'stale_import', 'bound_unverified', 'blocked', 'needs_repository', 'needs_binding', 'bound_attention', 'needs_matomo', 'needs_property', 'url_prefix_only'], true),
                                ])>
                                    {{ $summary['label'] }}
                                </span>
                            </div>
                            @if(! empty($summary['reason']))
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $summary['reason'] }}</p>
                            @endif
                            <a href="{{ $check['queue'] }}" wire:navigate class="mt-3 inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                Open related queue
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Search Console Issue Evidence</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Exact issue-detail evidence from Google Search Console drilldown exports lives here. Domain Monitor still keeps the baseline counts, but these snapshots add the concrete example URLs and crawl dates fleet remediation needs.
                        </p>
                    </div>

                    <div class="w-full max-w-xl rounded-lg border border-blue-200 dark:border-blue-800/60 bg-blue-50/70 dark:bg-blue-900/10 p-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Import Search Console drilldown ZIP
                        </div>
                        <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-center">
                            <input
                                type="file"
                                accept=".zip,application/zip"
                                wire:model="issueDetailArchive"
                                class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-700"
                            />
                            <button
                                type="button"
                                wire:click="importIssueDetail"
                                wire:loading.attr="disabled"
                                wire:target="importIssueDetail, issueDetailArchive"
                                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Import ZIP
                            </button>
                        </div>
                        @error('issueDetailArchive')
                            <div class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @if($searchConsoleIssueSummaries === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No Search Console issue evidence is stored for this property yet. Baseline summary counts will still flow into the issues feed where available.
                        </p>
                    @else
                        @foreach($searchConsoleIssueSummaries as $issue)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $issue['label'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if($issue['affected_url_count'] !== null)
                                                {{ $issue['affected_url_count'] }} affected URLs
                                            @else
                                                Count unavailable
                                            @endif
                                            @if($issue['captured_at'])
                                                · Captured {{ \Illuminate\Support\Carbon::parse($issue['captured_at'])->format('Y-m-d H:i') }}
                                            @endif
                                            @if($issue['source_capture_method'])
                                                · {{ str($issue['source_capture_method'])->replace('_', ' ')->title() }}
                                            @endif
                                        </div>
                                    </div>
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $issue['has_exact_examples'],
                                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => ! $issue['has_exact_examples'],
                                    ])>
                                        {{ $issue['has_exact_examples'] ? 'Exact examples captured' : 'Summary only' }}
                                    </span>
                                </div>

                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if($issue['source_report'])
                                        Source: {{ $issue['source_report'] }}
                                    @endif
                                    @if($issue['source_property'])
                                        · {{ $issue['source_property'] }}
                                    @endif
                                    @if($issue['first_detected'])
                                        · First detected {{ $issue['first_detected'] }}
                                    @endif
                                </div>

                                @if($issue['examples'] !== [])
                                    <div class="mt-3 space-y-2">
                                        @foreach($issue['examples'] as $example)
                                            <div class="rounded-md bg-gray-50 dark:bg-gray-900/50 px-3 py-2 text-xs text-gray-700 dark:text-gray-200 break-all">
                                                <div>{{ $example['url'] }}</div>
                                                @if($example['last_crawled'])
                                                    <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Last crawled {{ $example['last_crawled'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif($issue['sample_urls'] !== [])
                                    <div class="mt-3 space-y-2">
                                        @foreach($issue['sample_urls'] as $sampleUrl)
                                            <div class="rounded-md bg-gray-50 dark:bg-gray-900/50 px-3 py-2 text-xs text-gray-700 dark:text-gray-200 break-all">
                                                {{ $sampleUrl }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Linked Domains</h4>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $domains->count() }} linked</span>
                </div>

                <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Add Owned Subdomain</h5>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Link a real subdomain surface to this property so it appears under owned subdomains and can be safely excluded from apex normalization when needed.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr),minmax(0,1fr),auto]">
                        <div>
                            <label for="linked_subdomain_host" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Subdomain Host</label>
                            <input id="linked_subdomain_host" type="text" wire:model="linkedSubdomainHost" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="quoting.backloading-au.com.au" />
                            @error('linkedSubdomainHost') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label for="linked_subdomain_notes" class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</label>
                            <input id="linked_subdomain_notes" type="text" wire:model="linkedSubdomainNotes" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="Separate quoting surface" />
                            @error('linkedSubdomainNotes') <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                        </div>

                        <div class="flex items-end">
                            <button
                                type="button"
                                wire:click="saveLinkedSubdomain"
                                wire:loading.attr="disabled"
                                wire:target="saveLinkedSubdomain"
                                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Add Owned Subdomain
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h6 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Suggested Owned Subdomains</h6>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Likely web surfaces discovered under this property’s linked domains. Mail, auth, and service-only DNS records are excluded.
                                </p>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($suggestedOwnedSubdomains) }} suggested</span>
                        </div>

                        @if($suggestedOwnedSubdomains === [])
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                No likely web subdomains have been discovered yet.
                            </p>
                        @else
                            <div class="mt-3 space-y-2">
                                @foreach($suggestedOwnedSubdomains as $suggestion)
                                    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 px-3 py-3 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $suggestion['host'] }}</span>
                                                <span @class([
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $suggestion['resolution_state'] === 'resolves',
                                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $suggestion['resolution_state'] === 'unchecked',
                                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $suggestion['resolution_state'] === 'unresolved',
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($suggestion['resolution_state'], ['resolves', 'unchecked', 'unresolved'], true),
                                                ])>
                                                    {{ $suggestion['resolution_label'] }}
                                                </span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Discovered under {{ $suggestion['source_domain'] }}
                                            </div>
                                        </div>

                                        <div class="flex items-center md:justify-end">
                                            <button
                                                type="button"
                                                wire:click="useSuggestedOwnedSubdomain('{{ $suggestion['host'] }}')"
                                                class="inline-flex items-center justify-center rounded-md border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/20"
                                            >
                                                Use Suggestion
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">State</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Health</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">External Links</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($domains as $link)
                                @php
                                    $domain = $link->domain;
                                    $externalLinksScan = $domain?->externalLinksSummary() ?? \App\Models\Domain::emptyExternalLinksSummary();
                                @endphp
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $domain?->domain ?? 'Missing domain' }}</span>
                                            @if($link->is_canonical)
                                                <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                                                    Canonical
                                                </span>
                                            @endif
                                            @if($domain && ! $domain->is_active)
                                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Inactive
                                                </span>
                                            @endif
                                        </div>
                                        @if($domain?->dns_config_name)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $domain->dns_config_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        {{ ucfirst($link->usage_type) }}
                                        @if($link->notes)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $link->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-300">
                                        <div>{{ $domain?->platform ?? 'Unknown platform' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Alerts: {{ $domain?->alerts?->count() ?? 0 }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($domain)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach(['http', 'ssl', 'dns', 'seo'] as $checkType)
                                                    @php
                                                        $status = $domain->{'latest_'.$checkType.'_status'} ?? 'unknown';
                                                    @endphp
                                                    <span @class([
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $status === 'ok',
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $status === 'warn',
                                                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $status === 'fail',
                                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $status === 'unknown',
                                                    ])>
                                                        {{ strtoupper($checkType) }} {{ strtoupper($status) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                            @if($domain)
                                                @php
                                                    $relationshipCounts = collect($externalLinksScan['external_links'] ?? [])
                                                    ->countBy(fn (array $inventoryLink): string => (string) ($inventoryLink['relationship'] ?? 'external'))
                                                    ->all();
                                                $previewLinks = array_slice($externalLinksScan['external_links'] ?? [], 0, 2);
                                            @endphp
                                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span @class([
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $externalLinksScan['status'] === 'ok',
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $externalLinksScan['status'] === 'warn',
                                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $externalLinksScan['status'] === 'unknown',
                                                        'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => ! in_array($externalLinksScan['status'], ['ok', 'warn', 'unknown'], true),
                                                    ])>
                                                        {{ strtoupper($externalLinksScan['status']) }}
                                                    </span>
                                                    <span>{{ $externalLinksScan['external_links_count'] }} links</span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $externalLinksScan['unique_hosts_count'] }} hosts</span>
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $externalLinksScan['pages_scanned'] }} pages scanned
                                                    @if($externalLinksScan['checked_at'])
                                                        · {{ \Illuminate\Support\Carbon::parse($externalLinksScan['checked_at'])->diffForHumans() }}
                                                    @else
                                                        · not scanned yet
                                                    @endif
                                                </div>
                                            </div>

                                            @if(($externalLinksScan['external_links'] ?? []) !== [])
                                                <div class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                        @foreach(['subdomain' => 'Subdomains', 'parent_domain' => 'Parent domain', 'external' => 'External'] as $relationship => $label)
                                                            @if(($relationshipCounts[$relationship] ?? 0) > 0)
                                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-700">
                                                                    {{ $label }} {{ $relationshipCounts[$relationship] }}
                                                                </span>
                                                            @endif
                                                        @endforeach
                                                    </div>

                                                    <div class="mt-3 space-y-2">
                                                        @foreach($previewLinks as $externalLink)
                                                            @php
                                                                $previewUrl = (string) ($externalLink['url'] ?? '');
                                                                $safePreviewUrl = \Illuminate\Support\Str::startsWith($previewUrl, ['http://', 'https://'])
                                                                    ? $previewUrl
                                                                    : null;
                                                            @endphp
                                                            <div class="rounded-md bg-gray-50 px-3 py-2 text-xs dark:bg-gray-900/40">
                                                                <div class="truncate font-medium text-blue-600 dark:text-blue-400">
                                                                    @if($safePreviewUrl)
                                                                        <a href="{{ $safePreviewUrl }}" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                                                            {{ $previewUrl }}
                                                                        </a>
                                                                    @else
                                                                        <span class="text-gray-900 dark:text-gray-100">{{ $previewUrl }}</span>
                                                                    @endif
                                                                </div>
                                                                <div class="mt-1 flex flex-wrap items-center gap-2 text-gray-500 dark:text-gray-400">
                                                                    <span @class([
                                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                                        'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $externalLink['relationship'] === 'subdomain',
                                                                        'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' => $externalLink['relationship'] === 'parent_domain',
                                                                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($externalLink['relationship'], ['subdomain', 'parent_domain'], true),
                                                                    ])>
                                                                        {{ str_replace('_', ' ', $externalLink['relationship']) }}
                                                                    </span>
                                                                    @if($externalLink['found_on'])
                                                                        <span class="truncate">Found on {{ $externalLink['found_on'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    <div class="mt-3 flex items-center justify-between gap-3">
                                                        @if(count($externalLinksScan['external_links']) > count($previewLinks))
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ count($externalLinksScan['external_links']) - count($previewLinks) }} more links hidden from the table preview
                                                            </span>
                                                        @else
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                Full inventory available in the detail view
                                                            </span>
                                                        @endif

                                                        <button
                                                            type="button"
                                                            wire:click="openExternalLinksModal('{{ $domain->id }}')"
                                                            class="inline-flex items-center justify-center rounded-md border border-blue-200 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/20"
                                                        >
                                                            View all {{ count($externalLinksScan['external_links']) }} links
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if($domain)
                                            <a href="{{ route('domains.show', $domain->id) }}" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                                Open domain
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($showExternalLinksModal)
            <div
                class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60"
                wire:click="closeExternalLinksModal"
                wire:keydown.escape.window="closeExternalLinksModal"
            >
                <div
                    class="relative mx-auto mt-10 w-full max-w-5xl px-4 pb-10"
                    wire:click.stop
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="external-links-modal-title"
                    tabindex="-1"
                >
                    <div class="rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-700">
                            <div>
                                <h4 id="external-links-modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    External Link Inventory
                                </h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $selectedExternalLinksDomainName ?? 'Unknown domain' }}
                                    @if($selectedExternalLinksUsageType)
                                        · {{ ucfirst($selectedExternalLinksUsageType) }}
                                    @endif
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="closeExternalLinksModal"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Close
                            </button>
                        </div>

                        <div class="px-6 py-5">
                            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => ($selectedExternalLinksScan['status'] ?? 'unknown') === 'ok',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => ($selectedExternalLinksScan['status'] ?? 'unknown') === 'warn',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ($selectedExternalLinksScan['status'] ?? 'unknown') === 'unknown',
                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => ! in_array(($selectedExternalLinksScan['status'] ?? 'unknown'), ['ok', 'warn', 'unknown'], true),
                                ])>
                                    {{ strtoupper($selectedExternalLinksScan['status'] ?? 'unknown') }}
                                </span>
                                <span>{{ $selectedExternalLinksScan['external_links_count'] ?? 0 }} links</span>
                                <span>{{ $selectedExternalLinksScan['unique_hosts_count'] ?? 0 }} hosts</span>
                                <span>{{ $selectedExternalLinksScan['pages_scanned'] ?? 0 }} pages scanned</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    @if($selectedExternalLinksScan['checked_at'] ?? null)
                                        Last checked {{ \Illuminate\Support\Carbon::parse($selectedExternalLinksScan['checked_at'])->diffForHumans() }}
                                    @else
                                        Not scanned yet
                                    @endif
                                </span>
                            </div>

                            <div class="mt-5 space-y-3">
                                @if(($selectedExternalLinksScan['external_links'] ?? []) === [])
                                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        No external links were captured for this domain yet.
                                    </div>
                                @else
                                    @foreach(($selectedExternalLinksScan['external_links'] ?? []) as $externalLink)
                                        @php
                                            $modalUrl = (string) ($externalLink['url'] ?? '');
                                            $safeModalUrl = \Illuminate\Support\Str::startsWith($modalUrl, ['http://', 'https://'])
                                                ? $modalUrl
                                                : null;
                                        @endphp
                                        <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                                            <div class="break-all text-sm font-medium text-blue-600 dark:text-blue-400">
                                                @if($safeModalUrl)
                                                    <a href="{{ $safeModalUrl }}" target="_blank" rel="noopener noreferrer" class="hover:underline">
                                                        {{ $modalUrl }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-900 dark:text-gray-100">{{ $modalUrl }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span @class([
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                    'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $externalLink['relationship'] === 'subdomain',
                                                    'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' => $externalLink['relationship'] === 'parent_domain',
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($externalLink['relationship'], ['subdomain', 'parent_domain'], true),
                                                ])>
                                                    {{ str_replace('_', ' ', $externalLink['relationship']) }}
                                                </span>
                                                @if($externalLink['host'])
                                                    <span>Host {{ $externalLink['host'] }}</span>
                                                @endif
                                            </div>

                                            @if(($externalLink['found_on_pages'] ?? []) !== [])
                                                <div class="mt-3 rounded-md bg-gray-50 px-3 py-3 dark:bg-gray-900/40">
                                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Found On</div>
                                                    <div class="mt-2 space-y-1.5">
                                                        @foreach($externalLink['found_on_pages'] as $foundOnPage)
                                                            <div class="break-all text-xs text-gray-600 dark:text-gray-300">
                                                                {{ $foundOnPage }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Repositories</h4>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $repositories->count() }} linked</span>
                    </div>
                    <div class="mt-4 space-y-4">
                        @if($repositories->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">No repositories linked yet.</p>
                        @else
                            @foreach($repositories as $repository)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $repository->repo_name }}</div>
                                        @if($repository->is_primary)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Primary
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <div>Framework: {{ $repository->framework ?? 'Unknown' }}</div>
                                        <div>Provider: {{ $repository->repo_provider }}</div>
                                        <div class="break-all">Path: {{ $repository->local_path ?? 'Not set' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">Analytics Sources</h4>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $analyticsSources->count() }} linked</span>
                    </div>
                    <div class="mt-4 space-y-4">
                        @if($analyticsSources->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">No analytics sources linked yet.</p>
                        @else
                            @foreach($analyticsSources as $source)
                                @php
                                    $installAudit = $source->latestInstallAudit;
                                    $installVerdict = $installAudit?->install_verdict;
                                @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ strtoupper($source->provider) }}: {{ $source->external_id }}</div>
                                        @if($source->is_primary)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                Primary
                                            </span>
                                        @endif
                                        @if($installVerdict)
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $installVerdict === 'installed_match',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($installVerdict, ['partial_detection', 'unknown'], true),
                                                'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300' => in_array($installVerdict, ['not_detected', 'installed_wrong_site_id', 'installed_other_tracker_host', 'fetch_failed'], true),
                                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($installVerdict, ['installed_match', 'partial_detection', 'unknown', 'not_detected', 'installed_wrong_site_id', 'installed_other_tracker_host', 'fetch_failed'], true),
                                            ])>
                                                {{ str_replace('_', ' ', $installVerdict) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <div>Name: {{ $source->external_name ?? 'Not set' }}</div>
                                        <div>Status: {{ ucfirst($source->status) }}</div>
                                        <div class="break-all">Workspace: {{ $source->workspace_path ?? 'Not set' }}</div>
                                        @if($installAudit)
                                            <div>Tracker: {{ $installAudit->expected_tracker_host ?? 'Unknown' }}</div>
                                            <div>Checked: {{ $installAudit->checked_at?->diffForHumans() ?? 'Unknown' }}</div>
                                            @if($installAudit->best_url)
                                                <div class="break-all">Best URL: {{ $installAudit->best_url }}</div>
                                            @endif
                                            @if($installAudit->summary)
                                                <div>{{ $installAudit->summary }}</div>
                                            @endif
                                        @else
                                            <div>No install audit imported yet.</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

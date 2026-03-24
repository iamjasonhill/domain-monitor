@php
    $latestSecurityCheck = $domain->checks()->where('check_type', 'email_security')->latest()->first();
    $payload = $latestSecurityCheck?->payload ?? [];
    $spf = $payload['spf'] ?? null;
    $dmarc = $payload['dmarc'] ?? null;
    $dnssec = $payload['dnssec'] ?? null;
    $caa = $payload['caa'] ?? null;
    $dkim = $payload['dkim'] ?? null;
    $overallStatus = $payload['overall_status'] ?? $latestSecurityCheck?->status ?? 'unknown';
    $overallAssessment = $payload['overall_assessment'] ?? 'No assessment available.';
    $methodologyNote = $payload['methodology_note'] ?? 'Overall email security is based on verified SPF and DMARC. DKIM discovery, DNSSEC, and CAA are supporting signals only.';

    $helpers = [
        'spf' => 'Specifies which mail servers are authorized to send email on behalf of your domain.',
        'dmarc' => 'Tells email receivers how to handle mail that is not authenticated using SPF or DKIM.',
        'dkim' => 'Adds a cryptographic signature to emails. This panel only discovers selectors; it does not prove mail is being signed correctly.',
        'dnssec' => 'Protects DNS records from tampering. This is handled at the registrar or DNS host level.',
        'caa' => 'Limits which certificate authorities can issue certificates for your domain. This is advisory unless you intentionally manage CA restrictions.',
    ];

    $statusMeta = [
        'ok' => [
            'card' => 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800',
            'heading' => 'text-green-800 dark:text-green-300',
            'badge' => 'bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100',
            'label' => 'Meets Baseline',
        ],
        'warn' => [
            'card' => 'border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800',
            'heading' => 'text-yellow-800 dark:text-yellow-300',
            'badge' => 'bg-yellow-200 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
            'label' => 'Needs Review',
        ],
        'fail' => [
            'card' => 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800',
            'heading' => 'text-red-800 dark:text-red-300',
            'badge' => 'bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100',
            'label' => 'Missing Baseline',
        ],
        'unknown' => [
            'card' => 'border-gray-200 bg-gray-50 dark:bg-gray-900/20 dark:border-gray-800',
            'heading' => 'text-gray-800 dark:text-gray-300',
            'badge' => 'bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
            'label' => 'Could Not Verify',
        ],
    ];

    $overallMeta = $statusMeta[$overallStatus] ?? $statusMeta['unknown'];
    $spfMeta = $statusMeta[$spf['status'] ?? 'unknown'] ?? $statusMeta['unknown'];
    $dmarcMeta = $statusMeta[$dmarc['status'] ?? 'unknown'] ?? $statusMeta['unknown'];
    $dkimMeta = $statusMeta[$dkim['status'] ?? 'unknown'] ?? $statusMeta['unknown'];
    $dnssecMeta = $statusMeta[$dnssec['status'] ?? 'unknown'] ?? $statusMeta['unknown'];
    $caaMeta = $statusMeta[$caa['status'] ?? 'unknown'] ?? $statusMeta['unknown'];

    $mechanismLabels = [
        'hard_fail' => '-all',
        'soft_fail' => '~all',
        'neutral' => '?all',
        'allow_all' => '+all',
        'unknown' => 'unknown',
    ];
@endphp

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email Security</h3>
            <button wire:click="runHealthCheck('email_security')" wire:loading.attr="disabled" {{ $isParked ? 'disabled' : '' }} class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="runHealthCheck('email_security')">Run Security Check</span>
                <span wire:loading wire:target="runHealthCheck('email_security')">Checking...</span>
            </button>
        </div>

        @if($latestSecurityCheck)
            <div class="rounded-lg border p-4 mb-6 {{ $overallMeta['card'] }}">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Overall Email Baseline</p>
                        <p class="mt-1 text-sm {{ $overallMeta['heading'] }}">{{ $overallAssessment }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded {{ $overallMeta['badge'] }}">{{ $overallMeta['label'] }}</span>
                </div>
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">{{ $methodologyNote }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border rounded-md p-4 {{ $spfMeta['card'] }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold flex items-center {{ $spfMeta['heading'] }}">
                            SPF
                            <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full" x-cloak>
                                    {{ $helpers['spf'] }}
                                </div>
                            </div>
                        </h4>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $spfMeta['badge'] }}">{{ $spfMeta['label'] }}</span>
                    </div>

                    @if($spf)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Verified:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($spf['verified'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Present:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($spf['present'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            @if(!empty($spf['mechanism']))
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Enforcement:</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $mechanismLabels[$spf['mechanism']] ?? $spf['mechanism'] }}</span>
                                </div>
                            @endif
                            @if(!empty($spf['record']))
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                    <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $spf['record'] }}</code>
                                </div>
                            @endif
                            <div class="text-xs {{ ($spf['status'] ?? 'unknown') === 'fail' ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ $spf['assessment'] ?? 'No assessment available.' }}
                            </div>
                            @if(!empty($spf['error']))
                                <div class="text-red-600 dark:text-red-400 text-xs">Detail: {{ $spf['error'] }}</div>
                            @endif
                        </div>

                        @if(($spf['status'] ?? 'unknown') !== 'ok')
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 space-y-2">
                                <p class="font-semibold text-gray-700 dark:text-gray-300">How to improve</p>
                                @if(($spf['status'] ?? null) === 'warn')
                                    <p>Use this as a staged review point. Confirm every legitimate sender first, then move from <code>~all</code> to <code>-all</code> when you are confident the inventory is complete.</p>
                                @elseif(($spf['status'] ?? null) === 'unknown')
                                    <p>We could not verify SPF. Re-run the check and inspect DNS directly before making changes.</p>
                                @else
                                    <p>Create one authoritative SPF record based on the real systems that send mail for this domain. Do not publish a generic template unless it exactly matches your mail providers.</p>
                                @endif
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">No SPF data available.</p>
                    @endif
                </div>

                <div class="border rounded-md p-4 {{ $dmarcMeta['card'] }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold flex items-center {{ $dmarcMeta['heading'] }}">
                            DMARC
                            <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full" x-cloak>
                                    {{ $helpers['dmarc'] }}
                                </div>
                            </div>
                        </h4>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $dmarcMeta['badge'] }}">{{ $dmarcMeta['label'] }}</span>
                    </div>

                    @if($dmarc)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Verified:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dmarc['verified'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Present:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dmarc['present'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            @if(!empty($dmarc['policy']))
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Policy:</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $dmarc['policy'] }}</span>
                                </div>
                            @endif
                            @if(!empty($dmarc['record']))
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400 block mb-1">Record:</span>
                                    <code class="block w-full text-xs p-2 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-200 dark:border-gray-700 break-all">{{ $dmarc['record'] }}</code>
                                </div>
                            @endif
                            <div class="text-xs {{ ($dmarc['status'] ?? 'unknown') === 'fail' ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ $dmarc['assessment'] ?? 'No assessment available.' }}
                            </div>
                            @if(!empty($dmarc['error']))
                                <div class="text-red-600 dark:text-red-400 text-xs">Detail: {{ $dmarc['error'] }}</div>
                            @endif
                        </div>

                        @if(($dmarc['status'] ?? 'unknown') !== 'ok')
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 space-y-2">
                                <p class="font-semibold text-gray-700 dark:text-gray-300">How to improve</p>
                                @if(($dmarc['status'] ?? null) === 'warn')
                                    <p>This record is monitor-only. Review alignment and aggregate reports first, then move to <code>quarantine</code> or <code>reject</code> when the legitimate mail flow is confirmed.</p>
                                @elseif(($dmarc['status'] ?? null) === 'unknown')
                                    <p>We could not verify DMARC. Re-run the check and inspect the <code>_dmarc</code> TXT record directly before making changes.</p>
                                @else
                                    <p>Publish one DMARC record at <code>_dmarc</code> based on the real mail flow for this domain. Avoid copying a policy blindly, because the right enforcement level depends on alignment and reporting readiness.</p>
                                @endif
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">No DMARC data available.</p>
                    @endif
                </div>

                <div class="border rounded-md p-4 {{ $dkimMeta['card'] }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold flex items-center {{ $dkimMeta['heading'] }}">
                            DKIM
                            <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full" x-cloak>
                                    {{ $helpers['dkim'] }}
                                </div>
                            </div>
                        </h4>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $dkimMeta['badge'] }}">{{ $dkimMeta['label'] }}</span>
                    </div>

                    @if($dkim)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Verified:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dkim['verified'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Selectors Found:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dkim['present'] ?? false) ? count($dkim['selectors'] ?? []) : 'None' }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $dkim['assessment'] ?? 'No assessment available.' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Discovery mode: heuristic selector search.
                            </div>
                            @if(($dkim['present'] ?? false) && !empty($dkim['selectors']))
                                <div class="space-y-2">
                                    @foreach($dkim['selectors'] as $selector)
                                        <div>
                                            <span class="font-mono font-bold text-gray-700 dark:text-gray-300">{{ $selector['selector'] }}</span>
                                            <code class="block w-full mt-1 p-1.5 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-100 dark:border-gray-800 break-all">{{ $selector['record'] }}</code>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if(!empty($dkim['error']))
                                <div class="text-red-600 dark:text-red-400 text-xs">Detail: {{ $dkim['error'] }}</div>
                            @endif
                        </div>

                        @if(($dkim['status'] ?? 'unknown') !== 'ok')
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 space-y-2">
                                <p class="font-semibold text-gray-700 dark:text-gray-300">How to improve</p>
                                <p>Check your mail provider for the exact DKIM selector and TXT record, publish that record in DNS, then save the selector here to improve discovery. This panel does not prove mail signing unless a selector is found.</p>
                            </div>
                        @endif

                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label for="dkim_selectors" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Custom DKIM Selectors (comma separated)</label>
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
                            <p class="mt-1 text-[10px] text-gray-500">Add selectors used by your email providers to improve discovery accuracy.</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No DKIM data available.</p>
                    @endif
                </div>

                <div class="border rounded-md p-4 {{ $dnssecMeta['card'] }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold flex items-center {{ $dnssecMeta['heading'] }}">
                            DNSSEC
                            <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full" x-cloak>
                                    {{ $helpers['dnssec'] }}
                                </div>
                            </div>
                        </h4>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $dnssecMeta['badge'] }}">{{ $dnssecMeta['label'] }}</span>
                    </div>

                    @if($dnssec)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Verified:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dnssec['verified'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Enabled:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($dnssec['enabled'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $dnssec['assessment'] ?? 'No assessment available.' }}</div>
                            @if(!empty($dnssec['error']))
                                <div class="text-red-600 dark:text-red-400 text-xs">Detail: {{ $dnssec['error'] }}</div>
                            @endif
                        </div>

                        @if(($dnssec['status'] ?? 'unknown') !== 'ok')
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                                Enable DNSSEC through your registrar or authoritative DNS host. This is not set with a simple DNS record in the zone itself.
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">No DNSSEC data available.</p>
                    @endif
                </div>

                <div class="border rounded-md p-4 {{ $caaMeta['card'] }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold flex items-center {{ $caaMeta['heading'] }}">
                            CAA
                            <div x-data="{ open: false }" class="relative ml-1.5 inline-flex items-center">
                                <button @mouseenter="open = true" @mouseleave="open = false" @click="open = !open" type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 focus:outline-none">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute z-50 w-56 p-2 mt-2 text-xs font-normal text-white bg-gray-900 rounded-lg shadow-xl -left-1 sm:left-auto sm:right-0 top-full" x-cloak>
                                    {{ $helpers['caa'] }}
                                </div>
                            </div>
                        </h4>
                        <span class="px-2 py-1 text-xs font-bold rounded {{ $caaMeta['badge'] }}">{{ $caaMeta['label'] }}</span>
                    </div>

                    @if($caa)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Verified:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($caa['verified'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Present:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($caa['present'] ?? false) ? 'Yes' : 'No' }}</span>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $caa['assessment'] ?? 'No assessment available.' }}</div>
                            @if(($caa['present'] ?? false) && !empty($caa['records']))
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400 block mb-1">Authorized CAs:</span>
                                    <div class="space-y-1">
                                        @foreach($caa['records'] as $record)
                                            <code class="block w-full text-xs p-1.5 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 rounded border border-gray-100 dark:border-gray-800">{{ $record }}</code>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if(!empty($caa['error']))
                                <div class="text-red-600 dark:text-red-400 text-xs">Detail: {{ $caa['error'] }}</div>
                            @endif
                        </div>

                        @if(($caa['status'] ?? 'unknown') !== 'ok')
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                                Only add or change CAA records if you know which certificate authorities you intentionally use. This panel does not guess the right CA for you.
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">No CAA data available.</p>
                    @endif
                </div>
            </div>

            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                Last checked: {{ $latestSecurityCheck->created_at->diffForHumans() }} ({{ $latestSecurityCheck->duration_ms }}ms)
            </div>
        @else
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p>No security check data available yet.</p>
                <p class="text-sm mt-2">Click "Run Security Check" to evaluate SPF, DMARC, DKIM discovery, DNSSEC, and CAA.</p>
            </div>
        @endif
    </div>
</div>

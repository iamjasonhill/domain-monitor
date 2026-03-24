<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:justify-between lg:items-end">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 italic">Hosting Reliability</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">
                    Review hosting detections before trusting the provider rollups, then compare uptime and downtime by host.
                </p>
            </div>
            @if($selectedHost)
                <button wire:click="selectHost(null)" class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Overview
                </button>
            @endif
        </div>

        @if(session()->has('message'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                {{ session('message') }}
            </div>
        @endif

        @if(session()->has('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if(!$selectedHost)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Total Hosts</div>
                    <div class="text-3xl font-black text-gray-900 dark:text-gray-100">{{ $this->hostStats->count() }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Missing Provider</div>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400">{{ $this->reviewStats['missing'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Pending Review</div>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400">{{ $this->reviewStats['pending'] }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Reviewed</div>
                    <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $this->reviewStats['reviewed'] }}</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700 mb-8">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-gray-100">Hosting Review Queue</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Detect providers one domain at a time, then confirm the ones you trust before relying on the overview below.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach(['all' => 'All Review Items', 'missing' => 'Missing Provider', 'pending' => 'Pending Confirmation'] as $filter => $label)
                                <button
                                    wire:click="setReviewFilter('{{ $filter }}')"
                                    @class([
                                        'inline-flex items-center rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-widest transition-colors',
                                        'bg-blue-600 text-white hover:bg-blue-700' => $reviewFilter === $filter,
                                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' => $reviewFilter !== $filter,
                                    ])
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Detected Provider</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Confidence</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Review State</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @if($this->reviewQueue->isNotEmpty())
                                @foreach($this->reviewQueue as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 align-top">
                                        <div class="text-sm font-extrabold text-gray-900 dark:text-gray-100">{{ $item['domain'] }}</div>
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            @if($item['linked_properties'] !== [])
                                                @foreach($item['linked_properties'] as $property)
                                                    <a href="{{ route('web-properties.show', $property['slug']) }}" wire:navigate class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300 hover:underline">
                                                        {{ $property['name'] }}
                                                    </a>
                                                @endforeach
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-gray-400">No web property link</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        @if($item['hosting_provider'])
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['hosting_provider'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['hosting_detection_source'] ?? 'unknown source' }}
                                            </div>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-900/30 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                                Missing
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        @if($item['hosting_detection_confidence'])
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $item['hosting_detection_confidence'] === 'high',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $item['hosting_detection_confidence'] === 'medium',
                                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($item['hosting_detection_confidence'], ['high', 'medium'], true),
                                            ])>
                                                {{ strtoupper($item['hosting_detection_confidence']) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Not detected yet</span>
                                        @endif
                                        @if($item['hosting_detected_at'])
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['hosting_detected_at']->diffForHumans() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        @php
                                            $reviewState = $item['hosting_review_status'] ?: ($item['hosting_provider'] ? 'pending' : 'missing');
                                        @endphp
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($reviewState, ['missing', 'pending'], true),
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => in_array($reviewState, ['confirmed', 'manual'], true),
                                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => ! in_array($reviewState, ['missing', 'pending', 'confirmed', 'manual'], true),
                                        ])>
                                            {{ strtoupper($reviewState) }}
                                        </span>
                                        @if($item['hosting_reviewed_at'])
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Reviewed {{ $item['hosting_reviewed_at']->diffForHumans() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button
                                                wire:click="detectHostingForDomain('{{ $item['id'] }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="detectHostingForDomain('{{ $item['id'] }}')"
                                                class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50"
                                            >
                                                Detect
                                            </button>

                                            @if($item['hosting_provider'])
                                                <button
                                                    wire:click="confirmHostingForDomain('{{ $item['id'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmHostingForDomain('{{ $item['id'] }}')"
                                                    class="inline-flex items-center px-3 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 disabled:opacity-50"
                                                >
                                                    Confirm
                                                </button>
                                            @endif

                                            <a href="{{ route('domains.show', $item['id']) }}" wire:navigate class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600">
                                                Open Domain
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No domains currently need hosting review.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-xl font-black text-gray-900 dark:text-gray-100">Provider Overview</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        These groups use the current `hosting_provider` values. Treat them as reliable once domains move out of the review queue.
                    </p>
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hosting Provider</th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Domains</th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Incidents</th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Downtime</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Outage</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @if($this->hostStats->count() > 0)
                            @foreach($this->hostStats as $stat)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-extrabold text-gray-900 dark:text-gray-100">{{ $stat['host'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="px-2.5 py-1 text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded-full">
                                            {{ $stat['domain_count'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-medium {{ $stat['incident_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $stat['incident_count'] }}
                                            @if($stat['ongoing_count'] > 0)
                                                <span class="ml-1 animate-pulse text-xs text-red-500 font-bold">({{ $stat['ongoing_count'] }} live)</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            @if($stat['total_downtime_minutes'] > 60)
                                                {{ round($stat['total_downtime_minutes'] / 60, 1) }} hrs
                                            @else
                                                {{ $stat['total_downtime_minutes'] }} mins
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $stat['last_incident'] ? $stat['last_incident']->diffForHumans() : 'Never' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button wire:click="selectHost('{{ $stat['host'] }}')" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 font-bold">Details</button>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No hosting providers found yet. Use the review queue above to detect and confirm them.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-gray-100">{{ $selectedHost }} Breakdown</h3>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    @foreach($this->selectedHostDetails as $domain)
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700">
                            <div class="p-4 bg-gray-50 dark:bg-gray-900/50 flex justify-between items-center border-b border-gray-100 dark:border-gray-700">
                                <a href="{{ route('domains.show', $domain['domain_id']) }}" wire:navigate class="text-sm font-black text-gray-900 dark:text-gray-100 hover:text-blue-600 flex items-center gap-2">
                                    {{ $domain['domain'] }}
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                                <div class="text-xs font-bold space-x-4">
                                    <span class="text-gray-500 uppercase tracking-tighter">Incidents: <span class="text-gray-900 dark:text-gray-100">{{ $domain['incident_count'] }}</span></span>
                                    <span class="text-gray-500 uppercase tracking-tighter">Downtime: <span class="text-red-600 dark:text-red-400">{{ $domain['total_downtime'] }} mins</span></span>
                                    @if($domain['hosting_review_status'])
                                        <span class="text-gray-500 uppercase tracking-tighter">Review: <span class="text-gray-900 dark:text-gray-100">{{ $domain['hosting_review_status'] }}</span></span>
                                    @endif
                                </div>
                            </div>
                            @if($domain['incidents']->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                                        <thead class="bg-gray-50/50 dark:bg-gray-900/30">
                                            <tr>
                                                <th class="px-6 py-2 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">Started</th>
                                                <th class="px-6 py-2 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">Ended</th>
                                                <th class="px-6 py-2 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">Duration</th>
                                                <th class="px-6 py-2 text-left text-[10px] font-bold text-gray-400 uppercase tracking-widest">Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                            @foreach($domain['incidents'] as $incident)
                                                <tr class="text-xs">
                                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                        {{ $incident->started_at->format('M j, Y H:i') }}
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                        {{ $incident->ended_at ? $incident->ended_at->format('M j, Y H:i') : 'Ongoing' }}
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 font-mono">
                                                        {{ $incident->ended_at ? $incident->started_at->diffForHumans($incident->ended_at, true) : now()->diffForHumans($incident->started_at, true) }}
                                                    </td>
                                                    <td class="px-6 py-3 text-red-600 dark:text-red-400 italic">
                                                        {{ $incident->status_code ?? 'Error' }}: {{ $incident->error_message ?? 'Check failed' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-4 text-center text-xs text-gray-500">No specific incidents logged for this domain.</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

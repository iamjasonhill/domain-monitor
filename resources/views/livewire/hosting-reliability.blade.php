<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 italic">Hosting Reliability</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">Compare uptime and downtime across your hosting providers.</p>
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

        @if(!$selectedHost)
            <!-- Overview Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Total Hosts</div>
                    <div class="text-3xl font-black text-gray-900 dark:text-gray-100">{{ $this->hostStats->count() }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Total Incidents</div>
                    <div class="text-3xl font-black text-gray-900 dark:text-gray-100">{{ $this->hostStats->sum('incident_count') }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Total Downtime</div>
                    <div class="text-3xl font-black text-red-600 dark:text-red-400">
                        @php $totalMin = $this->hostStats->sum('total_downtime_minutes'); @endphp
                        {{ $totalMin > 60 ? round($totalMin/60, 1) . ' hrs' : $totalMin . ' mins' }}
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl overflow-hidden border border-gray-100 dark:border-gray-700">
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
                                            {{ round($stat['total_downtime_minutes']/60, 1) }} hrs
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
                                    No hosting providers found. Make sure your domains have a "hosting provider" set.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <!-- Host Detail View -->
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

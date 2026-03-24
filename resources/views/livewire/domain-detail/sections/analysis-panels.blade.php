<!-- Security Headers -->
@php
    $latestSecurityHeadersCheck = $domain->checks()
        ->where('check_type', 'security_headers')
        ->latest('started_at')
        ->first();
    $securityHeadersPayload = $latestSecurityHeadersCheck ? $latestSecurityHeadersCheck->payload : null;
    $securityHeaders = $securityHeadersPayload['results'] ?? [];
    $securityScore = $securityHeadersPayload['score'] ?? null;
@endphp
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Security Headers
            </h3>
            <div>
                @if($latestSecurityHeadersCheck)
                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{
                        $securityScore >= 80 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' :
                        ($securityScore >= 50 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' :
                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300')
                    }}">
                        Score: {{ $securityScore }}/100
                    </span>
                @else
                    <span class="text-gray-400 text-sm italic">Not checked</span>
                @endif
            </div>
        </div>

        @if($latestSecurityHeadersCheck)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($securityHeaders as $headerKey => $data)
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $data['name'] }}</h4>
                                <div class="mt-1">
                                    @if($data['status'] === 'pass')
                                        <span class="inline-flex items-center text-xs font-medium text-green-600 dark:text-green-400">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Present
                                        </span>
                                    @elseif($data['status'] === 'warn')
                                        <span class="inline-flex items-center text-xs font-medium text-yellow-600 dark:text-yellow-400">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            Review
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-xs font-medium text-red-600 dark:text-red-400">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            Missing
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($data['value'])
                            <div class="mt-2 group relative">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate cursor-help" title="{{ $data['value'] }}">
                                    {{ Str::limit($data['value'], 30) }}
                                </p>
                            </div>
                        @endif

                        @if($data['recommendation'])
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">
                                {{ $data['recommendation'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                Last checked: {{ $latestSecurityHeadersCheck->created_at->diffForHumans() }} ({{ $latestSecurityHeadersCheck->duration_ms }}ms)
            </div>
        @else
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p>No security headers data available yet.</p>
                <p class="text-sm mt-2">Run a health check to analyze security headers.</p>
            </div>
        @endif
    </div>
</div>

<!-- SEO Fundamentals -->
@php
    $latestSeoCheck = $domain->checks()
        ->where('check_type', 'seo')
        ->latest('started_at')
        ->first();
    $seoPayload = $latestSeoCheck ? $latestSeoCheck->payload : null;
    $seoResults = $seoPayload['results'] ?? [];
@endphp
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            SEO Fundamentals
        </h3>

        @if($latestSeoCheck)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Robots.txt</h4>
                            <div class="mt-1">
                                @if(($seoResults['robots']['exists'] ?? false))
                                    <span class="inline-flex items-center text-xs font-medium text-green-600 dark:text-green-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Found
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-xs font-medium text-red-600 dark:text-red-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        Missing
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if(($seoResults['robots']['url'] ?? false))
                            <a href="{{ $seoResults['robots']['url'] }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs flex items-center">
                                View
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sitemap.xml</h4>
                            <div class="mt-1">
                                @if(($seoResults['sitemap']['exists'] ?? false))
                                    <span class="inline-flex items-center text-xs font-medium text-green-600 dark:text-green-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Found
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-xs font-medium text-red-600 dark:text-red-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        Missing
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if(($seoResults['sitemap']['url'] ?? false))
                            <a href="{{ $seoResults['sitemap']['url'] }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline text-xs flex items-center">
                                View
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                Last checked: {{ $latestSeoCheck->created_at->diffForHumans() }} ({{ $latestSeoCheck->duration_ms }}ms)
            </div>
        @else
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p>No SEO data available yet.</p>
                <p class="text-sm mt-2">Run a health check to analyze SEO fundamentals.</p>
            </div>
        @endif
    </div>
</div>

<!-- Broken Link Checker -->
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                Broken Link Checker
            </h3>
            @if($domain->is_active)
                <!-- Action button or status could go here -->
            @endif
        </div>

        @php
            $latestBrokenLinkCheck = $domain->checks()->where('check_type', 'broken_links')->latest()->first();
            $blPayload = $latestBrokenLinkCheck?->payload ?? [];
            $brokenLinks = $blPayload['broken_links'] ?? [];
            $pagesScanned = $blPayload['pages_scanned'] ?? 0;
            $blCount = $blPayload['broken_links_count'] ?? count($brokenLinks);
        @endphp

        @if($latestBrokenLinkCheck)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                    <p class="text-lg font-semibold {{ $blCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $blCount > 0 ? 'Issues Found' : 'Healthy' }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Pages Scanned</span>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $pagesScanned }}</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Broken Links</span>
                    <p class="text-lg font-semibold {{ $blCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $blCount }}
                    </p>
                </div>
            </div>

            @if($blCount > 0)
                <div class="overflow-x-auto border rounded-lg dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Broken URL</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Found On</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($brokenLinks as $link)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 break-all">
                                        <a href="{{ $link['url'] }}" target="_blank" class="hover:underline text-blue-600 dark:text-blue-400">{{ $link['url'] }}</a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            {{ $link['status'] ?: 'Error' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 break-all">
                                        <a href="{{ $link['found_on'] }}" target="_blank" class="hover:underline">{{ $link['found_on'] }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex items-center p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 border border-green-200 dark:border-green-800" role="alert">
                    <svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>
                    <span class="sr-only">Info</span>
                    <div>
                        <span class="font-medium">No broken links found.</span> We scanned {{ $pagesScanned }} pages and found no dead links.
                    </div>
                </div>
            @endif

            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                Last checked: {{ $latestBrokenLinkCheck->created_at->diffForHumans() }} ({{ $latestBrokenLinkCheck->duration_ms }}ms)
            </div>
        @else
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p>No broken link data available yet.</p>
                <p class="text-sm mt-2">Run a health check to scan for broken links.</p>
            </div>
        @endif
    </div>
</div>

<!-- Reputation & Blacklist Monitoring -->
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reputation & Blacklist Monitoring</h3>
        </div>

        @php
            $latestReputationCheck = $domain->checks()->where('check_type', 'reputation')->latest()->first();
            $repPayload = $latestReputationCheck?->payload ?? [];
            $gsb = $repPayload['google_safe_browsing'] ?? null;
            $spamhaus = $repPayload['dnsbl']['spamhaus'] ?? null;
        @endphp

        @if($latestReputationCheck)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border rounded-md p-4 {{ $gsb && $gsb['safe'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">Google Safe Browsing</h4>
                        @if($gsb && $gsb['safe'])
                            <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">SAFE</span>
                        @else
                            <span class="px-2 py-1 text-xs font-bold rounded bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100">UNSAFE</span>
                        @endif
                    </div>
                    <div class="text-sm">
                        @if($gsb && !$gsb['safe'])
                            <div class="text-red-700 dark:text-red-300">
                                <p class="font-medium">Threats Detected:</p>
                                <ul class="list-disc list-inside mt-1">
                                    @foreach($gsb['matches'] as $match)
                                        <li>{{ $match['threatType'] }} ({{ $match['platformType'] }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif($gsb && $gsb['error'])
                            <p class="text-yellow-600 dark:text-yellow-400">Error: {{ $gsb['error'] }}</p>
                        @else
                            <p class="text-gray-600 dark:text-gray-400">No malware or phishing threats detected by Google.</p>
                        @endif
                    </div>
                </div>

                <div class="border rounded-md p-4 {{ $spamhaus && !$spamhaus['listed'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800' }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">Spamhaus Blocklist</h4>
                        @if($spamhaus && !$spamhaus['listed'])
                            <span class="px-2 py-1 text-xs font-bold rounded bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-100">CLEAN</span>
                        @else
                            <span class="px-2 py-1 text-xs font-bold rounded bg-red-200 text-red-800 dark:bg-red-800 dark:text-red-100">LISTED</span>
                        @endif
                    </div>
                    <div class="text-sm">
                        @if($spamhaus && $spamhaus['listed'])
                            <div class="text-red-700 dark:text-red-300">
                                <p class="font-medium">Listing Details:</p>
                                <p>{{ $spamhaus['details'] }}</p>
                                <p class="mt-2 text-xs"><a href="https://check.spamhaus.org/" target="_blank" class="underline hover:text-red-900">Visit Spamhaus Checker</a></p>
                            </div>
                        @elseif($spamhaus && $spamhaus['error'])
                            <p class="text-yellow-600 dark:text-yellow-400">Error: {{ $spamhaus['error'] }}</p>
                        @else
                            <p class="text-gray-600 dark:text-gray-400">Domain IP is not blacklisted by Spamhaus (Zen).</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-right">
                Last checked: {{ $latestReputationCheck->created_at->diffForHumans() }} ({{ $latestReputationCheck->duration_ms }}ms)
            </div>
        @else
            <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                <p>No reputation check data available yet.</p>
                <p class="text-sm mt-2">Click "Run Reputation Check" to scan for malware and blacklisting.</p>
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

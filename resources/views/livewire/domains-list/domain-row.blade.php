@php
    $isParked = $domain->isParked();
    $isManuallyParked = (bool) $domain->parked_override;
    $latestHttpCheck = $domain->checks->where('check_type', 'http')->first();
    $healthStatus = $latestHttpCheck ? $latestHttpCheck->status : null;
@endphp

<tr>
    <td class="px-6 py-4 whitespace-nowrap">
        <a href="{{ route('domains.show', $domain->id) }}" wire:navigate
            class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
            {{ $domain->domain }}
        </a>
        @if($isParked)
            <div class="mt-2">
                <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    Parked{{ $isManuallyParked ? ' (manual)' : '' }}
                </span>
            </div>
        @endif
        @if($domain->project_key)
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $domain->project_key }}</div>
        @endif
        @if($domain->tags && $domain->tags->count() > 0)
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach($domain->tags->sortByDesc('priority') as $tag)
                    <span class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full"
                        style="background-color: {{ $tag->color }}20; color: {{ $tag->color }}; border: 1px solid {{ $tag->color }}40;"
                        title="{{ $tag->description ?? $tag->name }}">
                        {{ $tag->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap">
        <div class="flex flex-col gap-1">
            @if($domain->is_active)
                <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
            @else
                <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
            @endif
            @if($isParked)
                <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    Parked
                </span>
            @elseif($healthStatus)
                @if($healthStatus === 'ok')
                    <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">✓
                        HTTP OK</span>
                @elseif($healthStatus === 'warn')
                    <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">⚠
                        HTTP Warn</span>
                @elseif($healthStatus === 'unknown')
                    <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">?
                        HTTP Unknown</span>
                @else
                    <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">✗
                        HTTP Failed</span>
                @endif
            @else
                <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">No
                    Checks</span>
            @endif
        </div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        @if($domain->expires_at)
            <div>{{ $domain->expires_at->format('Y-m-d') }}</div>
            @if($domain->expires_at->isPast())
                <div class="text-xs text-red-600 dark:text-red-400">Expired</div>
            @elseif($domain->expires_at->diffInDays(now()) <= 30)
                <div class="text-xs text-yellow-600 dark:text-yellow-400">
                    {{ $domain->expires_at->diffForHumans() }}</div>
            @else
                <div class="text-xs text-gray-400">{{ $domain->expires_at->diffForHumans() }}</div>
            @endif
        @else
            <span class="text-gray-400">N/A</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_ssl_status === 'fail')
            <span class="text-red-500" title="SSL Issue">❌</span>
        @elseif($domain->latest_ssl_status === 'warn')
            <span class="text-orange-500" title="Warning">⚠️</span>
        @elseif(in_array($domain->latest_ssl_status, ['pass', 'ok']))
            <span class="text-green-500" title="Valid">✓</span>
        @elseif($domain->latest_ssl_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_email_security_status === 'fail')
            <span class="text-red-500" title="Email Security Issue">❌</span>
        @elseif($domain->latest_email_security_status === 'warn')
            <span class="text-orange-500" title="Warning">⚠️</span>
        @elseif(in_array($domain->latest_email_security_status, ['pass', 'ok']))
            <span class="text-green-500" title="Valid">✓</span>
        @elseif($domain->latest_email_security_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_seo_status === 'fail')
            <span class="text-red-500" title="SEO Issue">❌</span>
        @elseif($domain->latest_seo_status === 'warn')
            <span class="text-orange-500" title="Warning">⚠️</span>
        @elseif(in_array($domain->latest_seo_status, ['pass', 'ok']))
            <span class="text-green-500" title="Valid">✓</span>
        @elseif($domain->latest_seo_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_reputation_status === 'fail')
            <span class="text-red-500" title="Reputation Issue">❌</span>
        @elseif($domain->latest_reputation_status === 'warn')
            <span class="text-orange-500" title="Warning">⚠️</span>
        @elseif(in_array($domain->latest_reputation_status, ['pass', 'ok']))
            <span class="text-green-500" title="Clean">✓</span>
        @elseif($domain->latest_reputation_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_security_headers_status === 'fail')
            <span class="text-red-500" title="Security Headers Issue">❌</span>
        @elseif($domain->latest_security_headers_status === 'warn')
            <span class="text-orange-500" title="Needs Improvement">⚠️</span>
        @elseif(in_array($domain->latest_security_headers_status, ['pass', 'ok']))
            <span class="text-green-500" title="Meets Baseline Standard">✓</span>
        @elseif($domain->latest_security_headers_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-2 py-4 whitespace-nowrap text-center text-sm">
        @if($domain->latest_broken_links_status === 'fail')
            <span class="text-red-500" title="Broken Links Found">❌</span>
        @elseif($domain->latest_broken_links_status === 'warn')
            <span class="text-orange-500" title="Warning">⚠️</span>
        @elseif(in_array($domain->latest_broken_links_status, ['pass', 'ok']))
            <span class="text-green-500" title="No Broken Links">✓</span>
        @elseif($domain->latest_broken_links_status === 'unknown')
            <span class="text-gray-500" title="Unknown">?</span>
        @else
            <span class="text-gray-300 text-xs">-</span>
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
        <a href="{{ route('domains.show', $domain->id) }}" wire:navigate
            class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-3">View</a>
        <a href="{{ route('domains.edit', $domain->id) }}" wire:navigate
            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Edit</a>
    </td>
</tr>

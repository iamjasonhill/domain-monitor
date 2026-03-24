<!-- Parked Domain Alert -->
@if($isParked)
    <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 text-yellow-800 dark:text-yellow-200 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <h4 class="font-semibold">This domain is parked</h4>
                <p class="text-sm mt-1">
                    @if($isManuallyParked)
                        This domain has been manually marked as parked. Health checks are disabled.
                    @else
                        This domain appears to be parked (detected). Health checks are disabled.
                    @endif
                </p>
                @if($isManuallyParked && $domain->parked_override_set_at)
                    <p class="text-xs mt-1 opacity-75">
                        Marked parked {{ $domain->parked_override_set_at->diffForHumans() }}.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif

<!-- Email Only Domain Alert -->
@if($domain->platform === 'Email Only')
    <div class="mb-6 p-4 bg-blue-100 dark:bg-blue-900 border border-blue-400 text-blue-800 dark:text-blue-200 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
            </svg>
            <div>
                <h4 class="font-semibold">Email Only Domain</h4>
                <p class="text-sm mt-1">This domain is configured for email only and does not have web hosting (no A records).</p>
            </div>
        </div>
    </div>
@endif

<!-- Domain Information (for Australian TLD domains) -->
@if($isAustralianTld && ($domain->registrant_name || $domain->eligibility_type || $domain->au_policy_id || $domain->au_compliance_reason || $domain->domain_roid))
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Australian Domain Information</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($domain->registrant_name)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrant</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registrant_name }}</dd>
                    </div>
                @endif
                @if($domain->registrant_id_type && $domain->registrant_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrant ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registrant_id_type }}: {{ $domain->registrant_id }}</dd>
                    </div>
                @endif
                @if($domain->eligibility_type)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Eligibility Type</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->eligibility_type }}</dd>
                    </div>
                @endif
                @if($domain->eligibility_valid !== null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Eligibility Status</dt>
                        <dd class="mt-1">
                            @if($domain->eligibility_valid)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Valid</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Invalid</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($domain->eligibility_last_check)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Eligibility Check</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->eligibility_last_check->format('Y-m-d') }}</dd>
                    </div>
                @endif
                @if($domain->au_policy_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Policy ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {{ $domain->au_policy_id }}
                            @if($domain->au_policy_desc)
                                <span class="text-gray-500 dark:text-gray-400"> - {{ $domain->au_policy_desc }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($domain->au_compliance_reason)
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Compliance Issue</dt>
                        <dd class="mt-1 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                            {{ $domain->au_compliance_reason }}
                        </dd>
                    </div>
                @endif
                @if($domain->au_association_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Association ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->au_association_id }}</dd>
                    </div>
                @endif
                @if($domain->domain_roid)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain ROID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono text-xs">{{ $domain->domain_roid }}</dd>
                    </div>
                @endif
                @if($domain->registry_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registry ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->registry_id }}</dd>
                    </div>
                @endif
                @if($domain->domain_status)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $domain->domain_status }}</dd>
                    </div>
                @endif
                @if($domain->categories && count($domain->categories) > 0)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Categories</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            <div class="flex flex-wrap gap-1">
                                @foreach($domain->categories as $category)
                                    <span class="px-2 py-0.5 inline-flex text-xs leading-4 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        {{ is_array($category) ? ($category['name'] ?? $category['id'] ?? json_encode($category)) : $category }}
                                    </span>
                                @endforeach
                            </div>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
@endif

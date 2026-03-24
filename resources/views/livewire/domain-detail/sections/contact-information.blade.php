<!-- Contact Information (for Australian TLD domains) -->
@php
    $latestContacts = $domain->latestContacts();
@endphp
@if($isAustralianTld && $latestContacts->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Contact Information</h3>
                @php
                    $latestSync = $latestContacts->first()?->synced_at;
                @endphp
                @if($latestSync)
                    <span class="text-xs text-gray-500 dark:text-gray-400">Last synced: {{ $latestSync->diffForHumans() }}</span>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $contactTypes = ['registrant', 'admin', 'tech', 'billing'];
                    $contactLabels = [
                        'registrant' => 'Registrant',
                        'admin' => 'Admin',
                        'tech' => 'Technical',
                        'billing' => 'Billing',
                    ];
                @endphp
                @foreach($contactTypes as $type)
                    @php
                        $contact = $domain->getContact($type);
                    @endphp
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ $contactLabels[$type] }}</h4>
                        @if($contact)
                            <dl class="space-y-2 text-sm">
                                @if($contact->name)
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100">{{ $contact->name }}</dd>
                                    </div>
                                @endif
                                @if($contact->getEmail())
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100 break-all">
                                            <a href="mailto:{{ $contact->getEmail() }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $contact->getEmail() }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                                @if($contact->getPhone())
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100">
                                            <a href="tel:{{ $contact->getPhone() }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $contact->getPhone() }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                                @if($contact->organization)
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Organization</dt>
                                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100">{{ $contact->organization }}</dd>
                                    </div>
                                @endif
                                @if($contact->getAddress() || $contact->city || $contact->state || $contact->postal_code || $contact->country)
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Address</dt>
                                        <dd class="mt-0.5 text-gray-900 dark:text-gray-100 text-xs">
                                            @if($contact->getAddress())
                                                {{ $contact->getAddress() }}<br>
                                            @endif
                                            @if($contact->city)
                                                {{ $contact->city }},
                                            @endif
                                            @if($contact->state)
                                                {{ $contact->state }}
                                            @endif
                                            @if($contact->postal_code)
                                                {{ $contact->postal_code }}
                                            @endif
                                            @if($contact->country)
                                                <br>{{ $contact->country }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">No contact information available</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

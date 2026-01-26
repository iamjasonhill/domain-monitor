<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DomainsList extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: false)]
    public string $search = '';

    #[Url(as: 'searchMode', history: true, keep: false)]
    public string $searchMode = 'all'; // 'all', 'domain', 'dns'

    #[Url(as: 'dnsType', history: true, keep: false)]
    public ?string $dnsType = null; // Filter by DNS record type (MX, A, CNAME, etc.)

    #[Url(as: 'dnsHost', history: true, keep: false)]
    public ?string $dnsHost = null; // Filter by DNS host/subdomain

    #[Url(as: 'dnsMissing', history: true, keep: false)]
    public bool $dnsMissing = false; // Show domains WITHOUT matching DNS records

    #[Url(as: 'active', history: true, keep: false)]
    public ?string $filterActive = null;

    #[Url(as: 'expiring', history: true, keep: false)]
    public bool $filterExpiring = false;

    #[Url(as: 'excludeParked', history: true, keep: false)]
    public bool $filterExcludeParked = false;

    #[Url(as: 'recentFailures', history: true, keep: false)]
    public bool $filterRecentFailures = false;

    #[Url(as: 'failedEligibility', history: true, keep: false)]
    public bool $filterFailedEligibility = false;

    #[Url(as: 'tag', history: true, keep: false)]
    public ?string $filterTag = null;

    #[Url(as: 'sort', history: true, keep: false)]
    public ?string $sortField = null;

    #[Url(as: 'dir', history: true, keep: false)]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        // Convert URL string values to proper types
        if ($this->filterActive !== null) {
            $this->filterActive = $this->filterActive === '1' ? '1' : ($this->filterActive === '0' ? '0' : null);
        }

        $this->sortField = $this->normalizeSortField($this->sortField);
        $this->sortDirection = $this->normalizeSortDirection($this->sortDirection);
    }

    public bool $syncingExpiry = false;

    public bool $syncingDns = false;

    public bool $importingDomains = false;

    public bool $detectingPlatforms = false;

    public function clearFilters(): void
    {
        $this->search = '';
        $this->searchMode = 'all';
        $this->dnsType = null;
        $this->dnsHost = null;
        $this->dnsMissing = false;
        $this->filterActive = null;
        $this->filterExpiring = false;
        $this->filterExcludeParked = false;
        $this->filterRecentFailures = false;
        $this->filterFailedEligibility = false;
        $this->filterTag = null;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSearchMode(): void
    {
        $this->resetPage();
    }

    public function updatedSearchMode(string $value): void
    {
        // Clear DNS-specific filters when switching away from DNS mode
        // $value is the NEW value after update
        if ($value !== 'dns' && $value !== 'all') {
            $this->dnsType = null;
            $this->dnsHost = null;
        }
    }

    public function updatingDnsType(): void
    {
        $this->resetPage();
    }

    public function updatingDnsHost(): void
    {
        $this->resetPage();
    }

    public function updatingDnsMissing(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    public function updatingFilterExpiring(): void
    {
        $this->resetPage();
    }

    public function updatingFilterExcludeParked(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRecentFailures(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFailedEligibility(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTag(): void
    {
        $this->resetPage();
    }

    /**
     * Get all available tags for the filter dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DomainTag>
     */
    #[Computed]
    public function availableTags(): \Illuminate\Database\Eloquent\Collection
    {
        return DomainTag::orderedByPriority()->get();
    }

    public function sortBy(string $field): void
    {
        $field = $this->normalizeSortField($field);
        if ($field === null) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    private function allowedSortFields(): array
    {
        return [
            'domain',
            'expires',
            'platform',
            'hosting',
            'active',
        ];
    }

    private function normalizeSortField(?string $field): ?string
    {
        if ($field === null || $field === '') {
            return null;
        }

        return in_array($field, $this->allowedSortFields(), true) ? $field : null;
    }

    private function normalizeSortDirection(string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param  Builder<Domain>  $query
     */
    private function applySorting(Builder $query, string $connection): void
    {
        if ($this->sortField === null) {
            return;
        }

        $dir = $this->normalizeSortDirection($this->sortDirection);

        // Ensure $dir is safe (already validated, but double-check)
        $safeDir = $dir === 'desc' ? 'desc' : 'asc';

        switch ($this->sortField) {
            case 'domain':
                $query->orderByRaw('LOWER(domains.domain) '.$safeDir);
                break;

            case 'expires':
                if ($connection === 'pgsql') {
                    $query->orderByRaw("domains.expires_at {$safeDir} NULLS LAST");
                } else {
                    $query->orderByRaw('domains.expires_at IS NULL ASC')
                        ->orderBy('domains.expires_at', $safeDir);
                }
                break;

            case 'hosting':
                if ($connection === 'pgsql') {
                    $query->orderByRaw("LOWER(domains.hosting_provider) {$safeDir} NULLS LAST");
                } else {
                    $query->orderByRaw('domains.hosting_provider IS NULL ASC')
                        ->orderByRaw('LOWER(domains.hosting_provider) '.$safeDir);
                }
                break;

            case 'active':
                $query->orderBy('domains.is_active', $safeDir);
                break;

            case 'platform':
                if ($connection === 'pgsql') {
                    $query->orderByRaw("LOWER(COALESCE(wp.platform_type, domains.platform)) {$safeDir} NULLS LAST");
                } else {
                    $query->orderByRaw('COALESCE(wp.platform_type, domains.platform) IS NULL ASC')
                        ->orderByRaw('LOWER(COALESCE(wp.platform_type, domains.platform)) '.$safeDir);
                }
                break;
        }

        // Stable tie-breaker to avoid jitter across pages
        $query->orderBy('domains.id', 'asc');
    }

    /**
     * Apply DNS record filters to a query builder
     * Used for both positive and negative searches
     *
     * @param  Builder<\App\Models\DnsRecord>|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyDnsRecordFilters(Builder $query): void
    {
        $connection = \DB::getDriverName();

        if (! empty($this->search)) {
            $searchTerm = trim($this->search);
            $searchTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);

            if ($connection === 'pgsql') {
                $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('host', 'ilike', '%'.$searchTerm.'%')
                        ->orWhere('type', 'ilike', '%'.$searchTerm.'%')
                        ->orWhere('value', 'ilike', '%'.$searchTerm.'%');
                });
            } else {
                $lowerTerm = mb_strtolower($searchTerm);
                $query->where(function ($subQuery) use ($lowerTerm) {
                    $subQuery->whereRaw('LOWER(host) LIKE ?', ['%'.$lowerTerm.'%'])
                        ->orWhereRaw('LOWER(type) LIKE ?', ['%'.$lowerTerm.'%'])
                        ->orWhereRaw('LOWER(value) LIKE ?', ['%'.$lowerTerm.'%']);
                });
            }
        }

        if ($this->dnsType) {
            if ($connection === 'pgsql') {
                $query->where('type', 'ilike', $this->dnsType);
            } else {
                $query->whereRaw('LOWER(type) = ?', [mb_strtolower($this->dnsType)]);
            }
        }

        if ($this->dnsHost) {
            if ($connection === 'pgsql') {
                $query->where('host', 'ilike', '%'.$this->dnsHost.'%');
            } else {
                $query->whereRaw('LOWER(host) LIKE ?', ['%'.mb_strtolower($this->dnsHost).'%']);
            }
        }
    }

    public function syncSynergyExpiry(): void
    {
        $this->syncingExpiry = true;

        try {
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active domain registrar credentials found. Please configure credentials first.', type: 'error');
                $this->syncingExpiry = false;

                return;
            }

            $exitCode = Artisan::call('domains:sync-synergy-expiry', ['--all' => true]);
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domain information synced successfully!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'Sync failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
            }
        } catch (\Exception $e) {
            \Log::error('Sync Synergy Expiry Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error syncing domain information: '.$e->getMessage(), type: 'error');
        } finally {
            $this->syncingExpiry = false;
            $this->resetPage();
        }
    }

    public function syncDnsRecords(): void
    {
        $this->syncingDns = true;

        try {
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active domain registrar credentials found. Please configure credentials first.', type: 'error');
                $this->syncingDns = false;

                return;
            }

            $exitCode = Artisan::call('domains:sync-dns-records', ['--all' => true]);
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'DNS records synced successfully!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'DNS sync failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
            }
        } catch (\Exception $e) {
            \Log::error('Sync DNS Records Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error syncing DNS records: '.$e->getMessage(), type: 'error');
        } finally {
            $this->syncingDns = false;
            $this->resetPage();
        }
    }

    public function importSynergyDomains(): void
    {
        $this->importingDomains = true;

        try {
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active domain registrar credentials found. Please configure credentials first.', type: 'error');
                $this->importingDomains = false;

                return;
            }

            $exitCode = Artisan::call('domains:import-synergy');
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domains imported successfully!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'Import failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
            }
        } catch (\Exception $e) {
            \Log::error('Import Synergy Domains Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error importing domains: '.$e->getMessage(), type: 'error');
        } finally {
            $this->importingDomains = false;
            $this->resetPage();
        }
    }

    public function detectPlatforms(): void
    {
        $this->detectingPlatforms = true;

        try {
            // Use --queue flag to avoid timeouts when detecting many domains
            $exitCode = Artisan::call('domains:detect-platforms', ['--all' => true, '--queue' => true]);
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            // Extract success count from output
            $successCount = null;
            foreach ($outputLines as $line) {
                if (preg_match('/Successfully detected platforms for (\d+)\/(\d+) domain/', $line, $matches)) {
                    $successCount = $matches[1].'/'.$matches[2];
                    break;
                }
            }

            if ($exitCode === 0) {
                // When using --queue, jobs are queued, not completed immediately
                if (str_contains($output, 'Queued') || str_contains($output, 'queued')) {
                    $this->dispatch('flash-message', message: 'Platform detection jobs have been queued. They will be processed by Horizon queue workers.', type: 'success');
                } else {
                    $message = $successCount
                        ? "Platform detection completed successfully for {$successCount} domain(s)!"
                        : 'Platform detection completed successfully!';
                    $this->dispatch('flash-message', message: $message, type: 'success');
                }
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'Platform detection failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
            }
        } catch (\Exception $e) {
            \Log::error('Detect Platforms Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error detecting platforms: '.$e->getMessage(), type: 'error');
        } finally {
            $this->detectingPlatforms = false;
            // Force component refresh to show updated platform data
            // Clear any cached relationships and refresh
            $this->resetPage();
            // Dispatch browser event to force full page refresh if needed
            $this->dispatch('platform-detection-complete');
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        // Convert filterActive string to boolean for scope
        $isActive = null;
        if ($this->filterActive === '1') {
            $isActive = true;
        } elseif ($this->filterActive === '0') {
            $isActive = false;
        }

        // Get database connection type for proper NULL handling
        $connection = \DB::getDriverName();

        $query = Domain::with([
            'platform',
            'tags',
        ])
            ->withLatestCheckStatuses()
            ->filterActive($isActive)
            ->filterExpiring($this->filterExpiring)
            ->excludeParked($this->filterExcludeParked)
            ->filterRecentFailures($this->filterRecentFailures)
            ->filterFailedEligibility($this->filterFailedEligibility)
            ->when($this->filterTag, function (Builder $query) {
                $query->whereHas('tags', function (Builder $q) {
                    $q->where('domain_tags.id', $this->filterTag);
                });
            })
            ->when(! empty($this->search), function (Builder $query) {
                if ($this->searchMode === 'dns') {
                    // DNS-only search
                    if ($this->dnsMissing) {
                        // Show domains WITHOUT matching DNS records
                        $query->whereDoesntHave('dnsRecords', function ($q) {
                            $this->applyDnsRecordFilters($q);
                        });
                    } else {
                        // Show domains WITH matching DNS records
                        $query->searchDns($this->search, $this->dnsType, $this->dnsHost);
                    }
                } elseif ($this->searchMode === 'domain') {
                    // Domain-only search (excludes DNS)
                    $query->search($this->search);
                } else {
                    // Search both domain fields and DNS records
                    if ($this->dnsMissing && ($this->searchMode === 'all' || $this->dnsType || $this->dnsHost)) {
                        // When "missing" is checked, search domain fields but exclude domains with matching DNS
                        $query->search($this->search)
                            ->whereDoesntHave('dnsRecords', function ($q) {
                                $this->applyDnsRecordFilters($q);
                            });
                    } else {
                        // Normal search: both domain and DNS
                        $query->where(function ($q) {
                            $q->search($this->search)
                                ->orWhere(function ($dnsQuery) {
                                    $dnsQuery->searchDns($this->search, $this->dnsType, $this->dnsHost);
                                });
                        });
                    }
                }
            })
            ->when(($this->searchMode === 'dns' || $this->searchMode === 'all') && empty($this->search) && ($this->dnsType || $this->dnsHost), function (Builder $query) use ($connection) {
                // Allow filtering by DNS type/host even without search term (works in both 'dns' and 'all' modes)
                if ($this->dnsMissing) {
                    // Show domains WITHOUT the specified DNS records
                    $query->whereDoesntHave('dnsRecords', function ($q) use ($connection) {
                        if ($this->dnsType) {
                            if ($connection === 'pgsql') {
                                $q->where('type', 'ilike', $this->dnsType);
                            } else {
                                $q->whereRaw('LOWER(type) = ?', [mb_strtolower($this->dnsType)]);
                            }
                        }
                        if ($this->dnsHost) {
                            if ($connection === 'pgsql') {
                                $q->where('host', 'ilike', '%'.$this->dnsHost.'%');
                            } else {
                                $q->whereRaw('LOWER(host) LIKE ?', ['%'.mb_strtolower($this->dnsHost).'%']);
                            }
                        }
                    });
                } else {
                    // Show domains WITH the specified DNS records
                    $query->whereHas('dnsRecords', function ($q) use ($connection) {
                        if ($this->dnsType) {
                            if ($connection === 'pgsql') {
                                $q->where('type', 'ilike', $this->dnsType);
                            } else {
                                $q->whereRaw('LOWER(type) = ?', [mb_strtolower($this->dnsType)]);
                            }
                        }
                        if ($this->dnsHost) {
                            if ($connection === 'pgsql') {
                                $q->where('host', 'ilike', '%'.$this->dnsHost.'%');
                            } else {
                                $q->whereRaw('LOWER(host) LIKE ?', ['%'.mb_strtolower($this->dnsHost).'%']);
                            }
                        }
                    });
                }
            })
            ->select('domains.*');

        // If a sort is selected, it overrides tag-priority ordering.
        if ($this->sortField !== null) {
            // Platform sorting needs a join (domain->platform is a relationship)
            if ($this->sortField === 'platform') {
                $query->leftJoin('website_platforms as wp', 'wp.domain_id', '=', 'domains.id');
            }

            $this->applySorting($query, $connection);
        } else {
            $query->leftJoin('domain_tag', 'domains.id', '=', 'domain_tag.domain_id')
                ->leftJoin('domain_tags', 'domain_tag.tag_id', '=', 'domain_tags.id')
                ->groupBy('domains.id');

            // Order by tag priority (handle NULL values based on database type)
            if ($connection === 'pgsql') {
                $query->orderByRaw('MAX(domain_tags.priority) DESC NULLS LAST');
            } else {
                // MySQL/SQLite: Use COALESCE to put NULLs last
                $query->orderByRaw('COALESCE(MAX(domain_tags.priority), -1) DESC');
            }

            $query->orderBy('domains.updated_at', 'DESC');
        }

        // Apply subquery scope after main select to ensure columns aren't overwritten
        $query->withLatestCheckStatuses();

        $domains = $query->paginate(50);

        return view('livewire.domains-list', [
            'domains' => $domains,
        ]);
    }
}

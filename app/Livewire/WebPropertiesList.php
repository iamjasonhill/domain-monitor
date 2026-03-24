<?php

namespace App\Livewire;

use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WebPropertiesList extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: false)]
    public string $search = '';

    #[Url(as: 'status', history: true, keep: false)]
    public ?string $filterStatus = null;

    #[Url(as: 'type', history: true, keep: false)]
    public ?string $filterType = null;

    #[Url(as: 'review', history: true, keep: false)]
    public bool $reviewQueue = false;

    #[Url(as: 'multiDomain', history: true, keep: false)]
    public bool $multiDomainOnly = false;

    #[Url(as: 'missingRepo', history: true, keep: false)]
    public bool $missingRepoOnly = false;

    #[Url(as: 'missingAnalytics', history: true, keep: false)]
    public bool $missingAnalyticsOnly = false;

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = null;
        $this->filterType = null;
        $this->reviewQueue = false;
        $this->multiDomainOnly = false;
        $this->missingRepoOnly = false;
        $this->missingAnalyticsOnly = false;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingReviewQueue(): void
    {
        $this->resetPage();
    }

    public function updatingMultiDomainOnly(): void
    {
        $this->resetPage();
    }

    public function updatingMissingRepoOnly(): void
    {
        $this->resetPage();
    }

    public function updatingMissingAnalyticsOnly(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function availablePropertyTypes(): array
    {
        /** @var array<int, string> $types */
        $types = WebProperty::query()
            ->whereNotNull('property_type')
            ->orderBy('property_type')
            ->pluck('property_type')
            ->unique()
            ->values()
            ->all();

        return $types;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $query = $this->baseQuery();

        return [
            'total' => (clone $query)->count(),
            'multi_domain' => (clone $query)->has('propertyDomains', '>', 1)->count(),
            'missing_repositories' => (clone $query)->doesntHave('repositories')->count(),
            'missing_analytics' => (clone $query)->doesntHave('analyticsSources')->count(),
        ];
    }

    public function render(): \Illuminate\View\View
    {
        $properties = $this->filteredQuery()
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.web-properties-list', [
            'properties' => $properties,
        ]);
    }

    /**
     * @return Builder<WebProperty>
     */
    private function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType) {
            $query->where('property_type', $this->filterType);
        }

        if ($this->multiDomainOnly) {
            $query->has('propertyDomains', '>', 1);
        }

        if ($this->missingRepoOnly) {
            $query->doesntHave('repositories');
        }

        if ($this->missingAnalyticsOnly) {
            $query->doesntHave('analyticsSources');
        }

        if ($this->reviewQueue) {
            $query->where(function (Builder $builder): void {
                $builder->has('propertyDomains', '>', 1)
                    ->orDoesntHave('repositories')
                    ->orDoesntHave('analyticsSources');
            });
        }

        $search = trim($this->search);
        if ($search !== '') {
            $connection = $query->getModel()->getConnection()->getDriverName();

            $query->where(function (Builder $builder) use ($search, $connection): void {
                if ($connection === 'pgsql') {
                    $builder->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('slug', 'ilike', '%'.$search.'%')
                        ->orWhere('notes', 'ilike', '%'.$search.'%')
                        ->orWhereHas('propertyDomains.domain', function (Builder $domainQuery) use ($search): void {
                            $domainQuery->where('domain', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('repositories', function (Builder $repoQuery) use ($search): void {
                            $repoQuery->where('repo_name', 'ilike', '%'.$search.'%');
                        });

                    return;
                }

                $lower = mb_strtolower($search);

                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$lower.'%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.$lower.'%'])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', ['%'.$lower.'%'])
                    ->orWhereHas('propertyDomains.domain', function (Builder $domainQuery) use ($lower): void {
                        $domainQuery->whereRaw('LOWER(domain) LIKE ?', ['%'.$lower.'%']);
                    })
                    ->orWhereHas('repositories', function (Builder $repoQuery) use ($lower): void {
                        $repoQuery->whereRaw('LOWER(repo_name) LIKE ?', ['%'.$lower.'%']);
                    });
            });
        }

        return $query;
    }

    /**
     * @return Builder<WebProperty>
     */
    private function baseQuery(): Builder
    {
        return WebProperty::query()
            ->withCount(['propertyDomains', 'repositories', 'analyticsSources'])
            ->with([
                'primaryDomain',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'propertyDomains.domain' => function ($query): void {
                    $query->withLatestCheckStatuses()
                        ->with(['tags', 'alerts' => fn ($alertQuery) => $alertQuery->whereNull('resolved_at')]);
                },
            ]);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use JsonException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

class PrReviewPassCommand extends Command
{
    private const REVIEW_THREAD_PAGE_SIZE = 50;

    protected $signature = 'pr:review-pass
                            {--with-preflight : Run pr:preflight before fetching PR status}
                            {--comments=5 : Number of recent top-level PR comments to show}';

    protected $description = 'Summarize the current branch pull request checks and review feedback';

    private const GIT_TIMEOUT_SECONDS = 15;

    private const GITHUB_TIMEOUT_SECONDS = 60;

    public function handle(): int
    {
        if ($this->option('with-preflight')) {
            $this->info('Running PR preflight before fetching PR status...');

            if ($this->call('pr:preflight') !== self::SUCCESS) {
                $this->error('PR review pass stopped because preflight failed.');

                return self::FAILURE;
            }

            $this->newLine();
        }

        $branch = null;

        try {
            $branchResult = $this->runGitCommand(['git', 'branch', '--show-current']);

            if ($branchResult->failed()) {
                $this->writeErrorResult($branchResult);
            } else {
                $resolvedBranch = trim($branchResult->output());

                if ($resolvedBranch !== '') {
                    $branch = $resolvedBranch;
                }
            }
        } catch (Throwable $exception) {
            $this->warn($exception->getMessage());
        }

        $this->info($branch !== null
            ? "Inspecting PR for branch {$this->sanitizeConsoleText($branch)}..."
            : 'Inspecting PR for the current checkout...'
        );

        $fields = implode(',', [
            'number',
            'title',
            'url',
            'mergeable',
            'reviewDecision',
            'statusCheckRollup',
            'latestReviews',
            'comments',
            'state',
            'isDraft',
            'headRefName',
            'baseRefName',
        ]);

        try {
            $prResult = $this->runGithubCommand(['gh', 'pr', 'view', '--json', $fields]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($prResult->failed()) {
            $this->writeErrorResult($prResult);
            $this->error($branch !== null
                ? "Unable to inspect an open PR for branch {$this->sanitizeConsoleText($branch)}. Make sure gh is authenticated and the branch has an open PR."
                : 'Unable to inspect an open PR for the current checkout. Make sure gh is authenticated and the checkout has an open PR.'
            );

            return self::FAILURE;
        }

        try {
            /** @var array{
             *   number:int,
             *   title:string,
             *   url:string,
             *   mergeable:string,
             *   reviewDecision:?string,
             *   state:string,
             *   isDraft:bool,
             *   headRefName:string,
             *   baseRefName:string,
             *   statusCheckRollup:array<int, array<string, mixed>>,
             *   latestReviews:array<int, array<string, mixed>>,
             *   comments:array<int, array<string, mixed>>
             * } $pullRequest
             */
            $pullRequest = json_decode($prResult->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Unable to decode GitHub PR metadata.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            'PR #%d: %s',
            $pullRequest['number'],
            $this->sanitizeConsoleText($pullRequest['title'])
        ));
        $this->line('URL: '.$this->sanitizeConsoleText($pullRequest['url']));
        $this->line(sprintf(
            'Branch: %s -> %s',
            $this->sanitizeConsoleText($pullRequest['headRefName']),
            $this->sanitizeConsoleText($pullRequest['baseRefName'])
        ));
        $this->line(sprintf(
            'State: %s | Draft: %s | Mergeable: %s | Review Decision: %s',
            $this->sanitizeConsoleText($pullRequest['state']),
            $pullRequest['isDraft'] ? 'yes' : 'no',
            $this->sanitizeConsoleText($pullRequest['mergeable']),
            filled($pullRequest['reviewDecision']) ? $this->sanitizeConsoleText((string) $pullRequest['reviewDecision']) : 'none'
        ));

        $statusRows = $this->statusRows(collect($pullRequest['statusCheckRollup']));

        if ($statusRows->isEmpty()) {
            $this->newLine();
            $this->warn('No status checks were reported by GitHub.');
        } else {
            $this->newLine();
            $this->table(['State', 'Name'], $statusRows->all());
            $this->printStatusSummary($statusRows);
        }

        $reviewRows = $this->reviewRows(collect($pullRequest['latestReviews']));

        if ($reviewRows->isNotEmpty()) {
            $this->newLine();
            $this->table(['Reviewer', 'State', 'Summary'], $reviewRows->all());
        }

        $commentRows = $this->commentRows(
            collect($pullRequest['comments']),
            max(0, (int) $this->option('comments'))
        );

        if ($commentRows->isNotEmpty()) {
            $this->newLine();
            $this->table(['Author', 'Summary'], $commentRows->all());
        }

        $inlineReviewRows = $this->inlineReviewRows($pullRequest['number']);

        if ($inlineReviewRows->isNotEmpty()) {
            $this->newLine();
            $this->table(['Author', 'File', 'Summary'], $inlineReviewRows->all());
        }

        $this->newLine();
        $this->info('PR review snapshot complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runGitCommand(array $command): ProcessResult
    {
        return Process::path(base_path())
            ->timeout(self::GIT_TIMEOUT_SECONDS)
            ->run($command);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runGithubCommand(array $command): ProcessResult
    {
        return Process::path(base_path())
            ->timeout(self::GITHUB_TIMEOUT_SECONDS)
            ->run($command);
    }

    private function writeErrorResult(ProcessResult $result): void
    {
        $errorOutput = trim($result->errorOutput());

        if ($errorOutput !== '') {
            $this->warn($errorOutput);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $statuses
     * @return Collection<int, array{0:string,1:string}>
     */
    private function statusRows(Collection $statuses): Collection
    {
        return $statuses
            ->map(function (array $status): array {
                $name = (string) ($status['__typename'] ?? '');

                if ($name === 'CheckRun') {
                    $label = trim(implode(' / ', array_filter([
                        (string) ($status['workflowName'] ?? ''),
                        (string) ($status['name'] ?? ''),
                    ])));

                    return [
                        strtoupper((string) ($status['conclusion'] ?? $status['status'] ?? 'UNKNOWN')),
                        $this->sanitizeConsoleText($label),
                    ];
                }

                return [
                    strtoupper((string) ($status['state'] ?? 'UNKNOWN')),
                    $this->sanitizeConsoleText((string) ($status['context'] ?? 'Status Context')),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{0:string,1:string}>  $statusRows
     */
    private function printStatusSummary(Collection $statusRows): void
    {
        $greenStates = ['SUCCESS', 'NEUTRAL', 'SKIPPED'];
        $failingStates = ['FAILURE', 'ERROR', 'CANCELLED', 'TIMED_OUT', 'ACTION_REQUIRED'];
        $pendingStates = ['PENDING', 'IN_PROGRESS', 'QUEUED', 'EXPECTED', 'WAITING'];

        $failingCount = $statusRows->filter(fn (array $row): bool => in_array($row[0], $failingStates, true))->count();
        $pendingCount = $statusRows->filter(fn (array $row): bool => in_array($row[0], $pendingStates, true))->count();
        $attentionCount = $statusRows->filter(function (array $row) use ($greenStates, $failingStates, $pendingStates): bool {
            return ! in_array($row[0], $greenStates, true)
                && ! in_array($row[0], $failingStates, true)
                && ! in_array($row[0], $pendingStates, true);
        })->count();

        if ($failingCount > 0) {
            $this->warn("Status checks currently failing: {$failingCount}");
        }

        if ($pendingCount > 0) {
            $this->warn("Status checks still pending: {$pendingCount}");
        }

        if ($attentionCount > 0) {
            $this->warn("Status checks need attention: {$attentionCount}");
        }

        if ($failingCount === 0 && $pendingCount === 0 && $attentionCount === 0) {
            $this->info('Status checks are green.');
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $reviews
     * @return Collection<int, array{0:string,1:string,2:string}>
     */
    private function reviewRows(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (array $review): bool => trim((string) ($review['state'] ?? '')) !== '')
            ->map(function (array $review): array {
                $author = (string) data_get($review, 'author.login', 'unknown');
                $state = (string) ($review['state'] ?? 'UNKNOWN');
                $summary = $this->summarizeText((string) ($review['body'] ?? ''));

                return [
                    $this->sanitizeConsoleText($author),
                    $this->sanitizeConsoleText($state),
                    $summary !== '' ? $this->sanitizeConsoleText($summary) : '-',
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $comments
     * @return Collection<int, array{0:string,1:string}>
     */
    private function commentRows(Collection $comments, int $limit): Collection
    {
        return $comments
            ->sortByDesc(fn (array $comment): string => (string) ($comment['createdAt'] ?? ''))
            ->take($limit)
            ->map(function (array $comment): array {
                return [
                    $this->sanitizeConsoleText((string) data_get($comment, 'author.login', 'unknown')),
                    $this->sanitizeConsoleText($this->summarizeText((string) ($comment['body'] ?? ''))),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{0:string,1:string,2:string}>
     */
    private function inlineReviewRows(int $pullRequestNumber): Collection
    {
        $repository = $this->githubRepository();

        if ($repository === null) {
            return collect();
        }

        $threads = $this->reviewThreads($repository, $pullRequestNumber);

        if ($threads->isEmpty()) {
            return collect();
        }

        return $threads
            ->filter(fn (array $thread): bool => ! (bool) ($thread['isResolved'] ?? false) && ! (bool) ($thread['isOutdated'] ?? false))
            ->flatMap(function (array $thread): Collection {
                return collect((array) data_get($thread, 'comments.nodes', []))
                    ->sortByDesc(fn (array $comment): string => (string) ($comment['createdAt'] ?? ''))
                    ->take(1)
                    ->map(function (array $comment): array {
                        return [
                            $this->sanitizeConsoleText((string) data_get($comment, 'author.login', 'unknown')),
                            $this->sanitizeConsoleText((string) ($comment['path'] ?? '-')),
                            $this->sanitizeConsoleText($this->summarizeText((string) ($comment['body'] ?? ''))),
                        ];
                    });
            })
            ->values();
    }

    /**
     * @param  array{owner:string,name:string}  $repository
     * @return Collection<int, array<string, mixed>>
     */
    private function reviewThreads(array $repository, int $pullRequestNumber): Collection
    {
        $query = sprintf(<<<'GRAPHQL'
query($owner: String!, $name: String!, $number: Int!, $after: String) {
  repository(owner: $owner, name: $name) {
    pullRequest(number: $number) {
      reviewThreads(first: %d, after: $after) {
        nodes {
          isResolved
          isOutdated
          comments(first: 10) {
            nodes {
              author {
                login
              }
              body
              path
              createdAt
            }
          }
        }
        pageInfo {
          hasNextPage
          endCursor
        }
      }
    }
  }
}
GRAPHQL, self::REVIEW_THREAD_PAGE_SIZE);

        $threadNodes = [];
        $cursor = null;

        do {
            try {
                $result = $this->runGithubCommand($this->reviewThreadsCommand(
                    $query,
                    $repository,
                    $pullRequestNumber,
                    $cursor
                ));
            } catch (Throwable $exception) {
                $this->warn($exception->getMessage());

                return collect();
            }

            if ($result->failed()) {
                $this->writeErrorResult($result);

                return collect();
            }

            try {
                /** @var array{
                 *   data?: array{
                 *     repository?: array{
                 *       pullRequest?: array{
                 *         reviewThreads?: array{
                 *           nodes?: array<int, array<string, mixed>>,
                 *           pageInfo?: array{
                 *             hasNextPage?: bool,
                 *             endCursor?: ?string
                 *           }
                 *         }
                 *       }
                 *     }
                 *   }
                 * } $payload
                 */
                $payload = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return collect();
            }

            $reviewThreads = data_get($payload, 'data.repository.pullRequest.reviewThreads', []);
            $threadNodes = array_merge($threadNodes, (array) data_get($reviewThreads, 'nodes', []));
            $hasNextPage = (bool) data_get($reviewThreads, 'pageInfo.hasNextPage', false);
            $nextCursor = data_get($reviewThreads, 'pageInfo.endCursor');
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;
        } while ($hasNextPage && $cursor !== null);

        return collect($threadNodes);
    }

    /**
     * @param  array{owner:string,name:string}  $repository
     * @return array<int, string>
     */
    private function reviewThreadsCommand(string $query, array $repository, int $pullRequestNumber, ?string $cursor): array
    {
        $command = [
            'gh',
            'api',
            'graphql',
            '-f',
            "query={$query}",
            '-F',
            "owner={$repository['owner']}",
            '-F',
            "name={$repository['name']}",
            '-F',
            "number={$pullRequestNumber}",
        ];

        if ($cursor !== null) {
            $command[] = '-F';
            $command[] = "after={$cursor}";
        }

        return $command;
    }

    /**
     * @return array{owner:string,name:string}|null
     */
    private function githubRepository(): ?array
    {
        try {
            $result = $this->runGitCommand(['git', 'remote', 'get-url', 'origin']);
        } catch (Throwable $exception) {
            $this->warn($exception->getMessage());

            return null;
        }

        if ($result->failed()) {
            $this->writeErrorResult($result);

            return null;
        }

        $remoteUrl = trim($result->output());

        if (preg_match('~github\.com[:/](?P<owner>[^/]+)/(?P<name>[^/.]+?)(?:\.git)?$~', $remoteUrl, $matches) !== 1) {
            return null;
        }

        return [
            'owner' => (string) $matches['owner'],
            'name' => (string) $matches['name'],
        ];
    }

    private function summarizeText(string $text): string
    {
        $summary = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $summary = strip_tags($summary);
        $summary = preg_replace('/`([^`]*)`/', '$1', $summary) ?? $summary;
        $summary = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $summary) ?? $summary;
        $summary = preg_replace('/\[(.*?)\]\([^)]*\)/', '$1', $summary) ?? $summary;
        $summary = preg_replace('/[#>*_|]+/', ' ', $summary) ?? $summary;
        $summary = preg_replace('/\s+/', ' ', $summary) ?? $summary;
        $summary = trim($summary);

        if ($summary === '') {
            return '';
        }

        return mb_strlen($summary) > 160
            ? mb_substr($summary, 0, 157).'...'
            : $summary;
    }

    private function sanitizeConsoleText(string $text): string
    {
        $sanitized = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/u', '', $text) ?? $text;
        $sanitized = preg_replace('/\x1B\][^\x07\x1B]*(?:\x07|\x1B\\\\)/u', '', $sanitized) ?? $sanitized;
        $sanitized = str_replace("\x1B", '', $sanitized);
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $sanitized) ?? $sanitized;

        return OutputFormatter::escape($sanitized);
    }
}

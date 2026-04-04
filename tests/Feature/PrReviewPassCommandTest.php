<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PrReviewPassCommandTest extends TestCase
{
    public function test_it_summarizes_the_current_branch_pull_request(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['git', 'branch', '--show-current']) {
                return Process::result('codex/add-pr-helper-commands');
            }

            if ($process->command === ['git', 'remote', 'get-url', 'origin']) {
                return Process::result('https://github.com/example/repo.git');
            }

            if ($process->command === ['gh', 'pr', 'view', '--json', 'number,title,url,mergeable,reviewDecision,statusCheckRollup,latestReviews,comments,state,isDraft,headRefName,baseRefName']) {
                return Process::result(json_encode([
                    'number' => 101,
                    'title' => 'Add PR helper commands',
                    'url' => 'https://github.com/example/repo/pull/101',
                    'mergeable' => 'MERGEABLE',
                    'reviewDecision' => '',
                    'state' => 'OPEN',
                    'isDraft' => false,
                    'headRefName' => 'codex/add-pr-helper-commands',
                    'baseRefName' => 'main',
                    'statusCheckRollup' => [
                        [
                            '__typename' => 'CheckRun',
                            'workflowName' => 'CI',
                            'name' => 'php',
                            'conclusion' => 'SUCCESS',
                            'status' => 'COMPLETED',
                        ],
                        [
                            '__typename' => 'StatusContext',
                            'context' => 'Devin Review',
                            'state' => 'PENDING',
                        ],
                    ],
                    'latestReviews' => [
                        [
                            'author' => ['login' => 'greptile-apps'],
                            'state' => 'COMMENTED',
                            'body' => 'Looks safe to merge.',
                        ],
                    ],
                    'comments' => [
                        [
                            'author' => ['login' => 'greptile-apps'],
                            'body' => '<h3>Greptile Summary</h3><p>No material issues remain.</p>',
                            'createdAt' => '2026-04-05T00:00:00Z',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if ($process->command[0] === 'gh' && $process->command[1] === 'api' && $process->command[2] === 'graphql') {
                return Process::result(json_encode([
                    'data' => [
                        'repository' => [
                            'pullRequest' => [
                                'reviewThreads' => [
                                    'nodes' => [
                                        [
                                            'isResolved' => false,
                                            'isOutdated' => false,
                                            'comments' => [
                                                'nodes' => [
                                                    [
                                                        'author' => ['login' => 'devin-ai'],
                                                        'body' => 'Please add a regression test.',
                                                        'path' => 'app/Console/Commands/PrReviewPassCommand.php',
                                                        'createdAt' => '2026-04-05T00:01:00Z',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            return Process::result('', 'unexpected command', 1);
        });

        $this->artisan('pr:review-pass')
            ->expectsOutputToContain('PR #101: Add PR helper commands')
            ->expectsOutputToContain('Branch: codex/add-pr-helper-commands -> main')
            ->expectsOutputToContain('Status checks still pending: 1')
            ->expectsOutputToContain('greptile-apps')
            ->expectsOutputToContain('devin-ai')
            ->expectsOutputToContain('PR review snapshot complete.')
            ->assertSuccessful();
    }

    public function test_it_stops_when_preflight_fails(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['./vendor/bin/phpstan', 'analyse', '--memory-limit=2G']) {
                return Process::result('', 'phpstan failed', 1);
            }

            return Process::result('ok');
        });

        $this->artisan('pr:review-pass', ['--with-preflight' => true])
            ->expectsOutputToContain('PR review pass stopped because preflight failed.')
            ->assertFailed();
    }

    public function test_it_fails_when_no_open_pr_can_be_found(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['git', 'branch', '--show-current']) {
                return Process::result('codex/add-pr-helper-commands');
            }

            if ($process->command === ['gh', 'pr', 'view', '--json', 'number,title,url,mergeable,reviewDecision,statusCheckRollup,latestReviews,comments,state,isDraft,headRefName,baseRefName']) {
                return Process::result('', 'no pull requests found', 1);
            }

            return Process::result('', 'unexpected command', 1);
        });

        $this->artisan('pr:review-pass')
            ->expectsOutputToContain('Unable to inspect an open PR for branch codex/add-pr-helper-commands.')
            ->assertFailed();
    }

    public function test_it_still_checks_github_when_the_branch_name_is_empty(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['git', 'branch', '--show-current']) {
                return Process::result('');
            }

            if ($process->command === ['git', 'remote', 'get-url', 'origin']) {
                return Process::result('https://github.com/example/repo.git');
            }

            if ($process->command === ['gh', 'pr', 'view', '--json', 'number,title,url,mergeable,reviewDecision,statusCheckRollup,latestReviews,comments,state,isDraft,headRefName,baseRefName']) {
                return Process::result(json_encode([
                    'number' => 102,
                    'title' => 'Detached head review',
                    'url' => 'https://github.com/example/repo/pull/102',
                    'mergeable' => 'MERGEABLE',
                    'reviewDecision' => '',
                    'state' => 'OPEN',
                    'isDraft' => false,
                    'headRefName' => 'codex/detached-head',
                    'baseRefName' => 'main',
                    'statusCheckRollup' => [],
                    'latestReviews' => [],
                    'comments' => [],
                ], JSON_THROW_ON_ERROR));
            }

            if ($process->command[0] === 'gh' && $process->command[1] === 'api' && $process->command[2] === 'graphql') {
                return Process::result(json_encode([
                    'data' => [
                        'repository' => [
                            'pullRequest' => [
                                'reviewThreads' => [
                                    'nodes' => [],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            return Process::result('', 'unexpected command', 1);
        });

        $this->artisan('pr:review-pass')
            ->expectsOutputToContain('Inspecting PR for the current checkout...')
            ->expectsOutputToContain('PR #102: Detached head review')
            ->assertSuccessful();
    }

    public function test_it_sanitizes_github_text_before_rendering_it(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['git', 'branch', '--show-current']) {
                return Process::result('codex/add-pr-helper-commands');
            }

            if ($process->command === ['git', 'remote', 'get-url', 'origin']) {
                return Process::result('https://github.com/example/repo.git');
            }

            if ($process->command === ['gh', 'pr', 'view', '--json', 'number,title,url,mergeable,reviewDecision,statusCheckRollup,latestReviews,comments,state,isDraft,headRefName,baseRefName']) {
                return Process::result(json_encode([
                    'number' => 103,
                    'title' => "\e[31mDangerous\e[0m <comment>tag</comment>",
                    'url' => 'https://github.com/example/repo/pull/103',
                    'mergeable' => 'MERGEABLE',
                    'reviewDecision' => '',
                    'state' => 'OPEN',
                    'isDraft' => false,
                    'headRefName' => 'codex/add-pr-helper-commands',
                    'baseRefName' => 'main',
                    'statusCheckRollup' => [],
                    'latestReviews' => [],
                    'comments' => [
                        [
                            'author' => ['login' => "bot\e]8;;https://malicious.example\x07"],
                            'body' => "Looks safe \e[32mnow\e[0m",
                            'createdAt' => '2026-04-05T00:00:00Z',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            if ($process->command[0] === 'gh' && $process->command[1] === 'api' && $process->command[2] === 'graphql') {
                return Process::result(json_encode([
                    'data' => [
                        'repository' => [
                            'pullRequest' => [
                                'reviewThreads' => [
                                    'nodes' => [],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            return Process::result('', 'unexpected command', 1);
        });

        $this->artisan('pr:review-pass')
            ->expectsOutputToContain('Dangerous')
            ->expectsOutputToContain('Looks safe now')
            ->assertSuccessful();
    }

    public function test_it_marks_unknown_terminal_states_as_needing_attention(): void
    {
        Process::preventStrayProcesses();
        Process::fake(function (PendingProcess $process) {
            if ($process->command === ['git', 'branch', '--show-current']) {
                return Process::result('codex/add-pr-helper-commands');
            }

            if ($process->command === ['git', 'remote', 'get-url', 'origin']) {
                return Process::result('https://github.com/example/repo.git');
            }

            if ($process->command === ['gh', 'pr', 'view', '--json', 'number,title,url,mergeable,reviewDecision,statusCheckRollup,latestReviews,comments,state,isDraft,headRefName,baseRefName']) {
                return Process::result(json_encode([
                    'number' => 104,
                    'title' => 'Unexpected check state',
                    'url' => 'https://github.com/example/repo/pull/104',
                    'mergeable' => 'MERGEABLE',
                    'reviewDecision' => '',
                    'state' => 'OPEN',
                    'isDraft' => false,
                    'headRefName' => 'codex/add-pr-helper-commands',
                    'baseRefName' => 'main',
                    'statusCheckRollup' => [
                        [
                            '__typename' => 'StatusContext',
                            'context' => 'External Review',
                            'state' => 'STARTUP_FAILURE',
                        ],
                    ],
                    'latestReviews' => [],
                    'comments' => [],
                ], JSON_THROW_ON_ERROR));
            }

            if ($process->command[0] === 'gh' && $process->command[1] === 'api' && $process->command[2] === 'graphql') {
                return Process::result(json_encode([
                    'data' => [
                        'repository' => [
                            'pullRequest' => [
                                'reviewThreads' => [
                                    'nodes' => [],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            return Process::result('', 'unexpected command', 1);
        });

        $this->artisan('pr:review-pass')
            ->expectsOutputToContain('Status checks need attention: 1')
            ->assertSuccessful();
    }
}

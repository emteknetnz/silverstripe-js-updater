<?php

namespace emteknetnz\JsUpdater\Services;

use Github\Client;
use Github\Api\PullRequest;
use Symfony\Component\Console\Output\OutputInterface;

class GitHubService
{
    private const FORK_GITHUB_ACCOUNT = 'creative-commoners';

    private const PR_TITLE = 'DEP Update JS dependencies';

    /**
     * Array of supported modules that have not been migrated to the silverstripe account
     */
    private const NON_MIGRATED_GH_REPOS = [
        'bringyourownideas/silverstripe-maintenance',
        'symbiote/silverstripe-multivaluefield',
        'tractorcow-farm/silverstripe-fluent',
    ];

    public function __construct(
        private Client $githubClient
    ) {
    }

    public function createPullRequest(
        string $ghrepo,
        string $githubIssueUrl,
        string $headBranch,
        string $baseBranch,
        OutputInterface $output,
    ): void {
        [$account, $repoName] = explode('/', $ghrepo);
        if ($account !== 'silverstripe' && !in_array($ghrepo, GitHubService::NON_MIGRATED_GH_REPOS)) {
            $account = 'silverstripe';
        }
        $forkAccount = GitHubService::FORK_GITHUB_ACCOUNT;
        $output->writeln("<info>Creating pull request to $account/$repoName</info>");
        /** @var PullRequest $pullRequest */
        $pullRequest = $this->githubClient->api('pull_request');
        $pullRequest->create($account, $repoName, [
            'title' => GitHubService::PR_TITLE,
            'body' => $this->getBody($githubIssueUrl),
            'head' => "$forkAccount:$headBranch",
            'base' => $baseBranch,
        ]);
    }

    private function getBody(string $githubIssueUrl): string
    {
        return <<<TEXT
        Issue $githubIssueUrl
        TEXT;
    }
}

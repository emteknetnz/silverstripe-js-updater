<?php

namespace emteknetnz\JsUpdater\Services;

use Github\Client;
use Github\Api\PullRequest;

class GitHubService
{
    private const PR_GITHUB_ACCOUNT = 'creative-commoners';

    private const PR_TITLE = 'DEP Update JS dependencies';

    public function __construct(
        private Client $githubClient
    ) {
    }

    public function createPullRequest(
        string $module,
        string $githubIssueUrl,
        string $headBranch,
        string $baseBranch,
    ): void {
        /** @var PullRequest $pullRequest */
        $pullRequest = $this->githubClient->api('pull_request');
        $pullRequest->create(GitHubService::PR_GITHUB_ACCOUNT, $module, [
            'title' => GitHubService::PR_TITLE,
            'body' => $this->getBody($githubIssueUrl),
            'head' => $headBranch,
            'base' => $baseBranch,
        ]);
    }

    private function getBody(string $githubIssueUrl): string
    {
        return <<<TEXT
        Issue: $githubIssueUrl
        TEXT;
    }
}

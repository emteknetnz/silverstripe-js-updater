<?php

namespace emteknetnz\JsUpdater\Services;

use Github\Client;
use Github\Api\PullRequest;
use Github\AuthMethod;
use Symfony\Component\Console\Output\OutputInterface;

class GitHubService
{
    /**
     * This is the github account that forks are made to
     */
    private const FORK_GITHUB_ACCOUNT = 'creative-commoners';

    /**
     * This is the title of the pull request
     */
    private const PR_TITLE = 'DEP Update JS dependencies';

    /**
     * Array of supported modules that have not been migrated to the silverstripe account
     */
    private const NON_MIGRATED_GH_REPOS = [
        'bringyourownideas/silverstripe-maintenance',
        'symbiote/silverstripe-multivaluefield',
        'tractorcow-farm/silverstripe-fluent',
    ];

    /**
     * Client for communicating with GitHub API
     */
    private Client $githubClient;

    /**
     * Authenticates the GitHub client.
     */
    public function __construct() {
        $this->githubClient = new Client;
        $this->githubClient->authenticate($_ENV['GITHUB_TOKEN'], null, AuthMethod::ACCESS_TOKEN);
    }

    /**
     * Creates a pull request on GitHub to the silverstripe account or a non-migrated repo.
     */
    public function createPullRequest(
        string $ghrepo,
        string $githubIssueUrl,
        string $headBranch,
        string $baseBranch,
        OutputInterface $output,
    ): array {
        [$account, $repoName] = explode('/', $ghrepo);
        if ($account !== 'silverstripe' && !in_array($ghrepo, GitHubService::NON_MIGRATED_GH_REPOS)) {
            $account = 'silverstripe';
        }
        $forkAccount = GitHubService::FORK_GITHUB_ACCOUNT;
        $head = "$forkAccount:$headBranch";
        $message = "Creating pull request to '$account/$repoName' with head '$head' and base '$baseBranch'";
        $output->writeln("<info>$message</info>");
        /** @var PullRequest $pullRequest */
        $pullRequest = $this->githubClient->api('pull_request');
        return $pullRequest->create($account, $repoName, [
            'title' => GitHubService::PR_TITLE,
            'body' => $this->getBody($githubIssueUrl),
            'head' => "$forkAccount:$headBranch",
            'base' => $baseBranch,
        ]);
    }

    /**
     * Gets the body for a pull request
     */
    private function getBody(string $githubIssueUrl): string
    {
        return <<<TEXT
        Issue $githubIssueUrl
        TEXT;
    }
}

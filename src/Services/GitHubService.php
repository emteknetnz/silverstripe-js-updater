<?php

namespace emteknetnz\JsUpdater\Services;

use Github\Client;

class GitHubService
{
    public function __construct(
        private Client $githubClient
    ) {
    }

    public function getRepositoryData(string $owner, string $repository): array
    {
        return $this->githubClient->api('repo')->show($owner, $repository);
    }
}

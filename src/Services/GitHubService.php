<?php

namespace emteknetnz\JsUpdater\Services;

use Github\Client;

class GitHubService
{
    public function __construct(
        // todo: should automatically get added in services.yml
        private Client $githubClient
    ) {
    }

    public function test() {
        var_dump($this->githubClient);die;
    }

    public function getRepositoryData(string $owner, string $repository): array
    {
        // return $this->githubClient->api('repo')->show($owner, $repository);
        return [];
    }
}

<?php

namespace emteknetnz\JsUpdater\Services;

use InvalidArgumentException;
use SilverStripe\SupportedModules\MetaData;

class ModuleService
{
    /**
     * Return an array of all supported modules with a package.json file that exist in a vendor folder
     *
     * @return array<string>
     */
    function getSupportedJsModules(string $as = 'packagist'): array
    {
        if (!in_array($as, ['packagist', 'github', 'github_url'])) {
            throw new InvalidArgumentException('Unsupported $as value: ' . $as);
        }
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        $modules = [];
        $metadata = MetaData::getAllRepositoryMetaData();
        foreach ($metadata['supportedModules'] as $data) {
            $subdir = $data['packagist'];
            if (!file_exists("$vendorDir/$subdir/package.json")) {
                continue;
            }
            if ($as === 'packagist') {
                $modules[] = $data['packagist'];
            } else if ($as === 'github') {
                $modules[] = $data['github'];
            } else if ($as === 'github_url') {
                $modules[] = "https://github.com/{$data['github']}";
            }
        }
        sort($modules);
        return $modules;
    }
}
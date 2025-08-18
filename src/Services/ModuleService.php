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
        if (!in_array($as, ['packagist', 'github'])) {
            throw new InvalidArgumentException('Unsupported $as value: ' . $as);
        }
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        $modules = [];
        $metadata = MetaData::getAllRepositoryMetaData(false);
        foreach ($metadata as $data) {
            $subdir = $data['packagist'] ?? null;
            if (!$subdir) {
                continue;
            }
            if (file_exists("$vendorDir/$subdir/package.json")) {
                if ($as === 'packagist') {
                    $modules[] = $subdir;
                } else if ($as === 'github') {
                    $modules[] = "https://github.com/{$data['github']}";
                }
            }
        }
        sort($modules);
        return $modules;
    }
}
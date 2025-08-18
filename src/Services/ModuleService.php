<?php

namespace emteknetnz\JsUpdater\Services;

use SilverStripe\SupportedModules\MetaData;

class ModuleService
{
    /**
     * Return an array of all supported modules with a package.json file that exist in a vendor folder
     *
     * @return array<string>
     */
    function getSupportedJsModules(): array
    {
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        $modules = [];
        $metadata = MetaData::getAllRepositoryMetaData(false);
        foreach ($metadata as $data) {
            $subdir = $data['packagist'] ?? null;
            if (!$subdir) {
                continue;
            }
            if (file_exists("$vendorDir/$subdir/package.json")) {
                $modules[] = $subdir;
            }
        }
        sort($modules);
        return $modules;
    }
}
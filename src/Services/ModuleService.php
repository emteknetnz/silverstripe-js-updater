<?php

namespace emteknetnz\JsUpdater\Services;

use InvalidArgumentException;
use SilverStripe\SupportedModules\MetaData;

class ModuleService
{
    private array $metadata;

    public function __construct()
    {
        $this->metadata = MetaData::getAllRepositoryMetaData();
    }

    /**
     * Return an array of all supported modules with a package.json file that exist in a vendor folder
     */
    public function getSupportedJsModules(): array
    {
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        $modules = [];
        foreach ($this->metadata['supportedModules'] as $data) {
            $subdir = $data['packagist'];
            if (!file_exists("$vendorDir/$subdir/package.json")) {
                continue;
            }
            $modules[] = $data['packagist'];
        }
        sort($modules);
        return $modules;
    }

    public function getGitHubFromModule(string $module)
    {
        foreach ($this->metadata['supportedModules'] as $data) {
            if ($data['packagist'] === $module) {
                return $data['github'];
            }
        }
        throw new InvalidArgumentException("Module '$module' was not found in metadata");
    }

    public function getModuleFromGitHub(string $github)
    {
        foreach ($this->metadata['supportedModules'] as $data) {
            if ($data['github'] === $github) {
                return $data['packagist'];
            }
        }
        throw new InvalidArgumentException("GitHub '$github' was not found in metadata");
    }
}
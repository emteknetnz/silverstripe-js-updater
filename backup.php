<?php

use SilverStripe\SupportedModules\MetaData;

$vendorDir = dirname(dirname(__DIR__));

include $vendorDir . '/autoload.php';

$jsModules = [];

$metadata = MetaData::getAllRepositoryMetaData(false);

foreach ($metadata as $data) {
    $subdir = $data['packagist'] ?? null;
    if (!$subdir) {
        continue;
    }
    if (file_exists("$vendorDir/$subdir/package.json")) {
        $jsModules[] = $subdir;
    }
}

sort($jsModules);

foreach ($jsModules as $module) {
    echo $module . PHP_EOL;
}

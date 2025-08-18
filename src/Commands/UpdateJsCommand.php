<?php

namespace emteknetnz\JsUpdater\Commands;

use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'update-js',
    description: 'The default command.',
)]
class UpdateJsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from the default command!');
        $vendorDir = dirname(dirname(__DIR__));
        $jsModules = [];
        $metadata = MetaData::getAllRepositoryMetaData(false);
        var_dump($metadata);die;
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
        return Command::SUCCESS;
    }
}

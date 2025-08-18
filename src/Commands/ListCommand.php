<?php

namespace emteknetnz\JsUpdater\Commands;

use emteknetnz\JsUpdater\Services\ModuleService;
use SilverStripe\SupportedModules\MetaData;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'list',
    description: 'Lists all supported modules in current project with a package.json file',
)]
class ListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = (new ModuleService)->getSupportedJsModules();
        var_dump($modules);
        foreach ($modules as $module) {
            $output->writeln($module);
        }
        return Command::SUCCESS;
    }
}

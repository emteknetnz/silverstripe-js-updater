<?php

namespace emteknetnz\JsUpdater\Commands;

use emteknetnz\JsUpdater\Services\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'show',
    description: 'Show all supported modules in current project with a package.json file',
)]
class ShowCommand extends Command
{
    /**
     * Defines the command options.
     */
    protected function configure(): void
    {
        $this->addOption(
            'github',
            'g',
            description: 'List github urls'
        );
    }

    /**
     * Outputs a list of supported modules.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $as = $input->getOption('github') ? 'github' : 'packagist';
        $modules = (new ModuleService())->getSupportedJsModules($as);
        foreach ($modules as $module) {
            $output->writeln($module);
        }
        return Command::SUCCESS;
    }
}

<?php

namespace emteknetnz\JsUpdater\Commands;

use emteknetnz\JsUpdater\Services\GitHubService;
use InvalidArgumentException;
use RuntimeException;
use emteknetnz\JsUpdater\Services\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Github\Client;

#[AsCommand(
    name: 'update',
    description: 'Update supported modules in current project',
)]
class UpdateCommand extends BaseCommand
{
    private OutputInterface $output;

    protected function configure(): void
    {
        $admin = 'silverstripe/admin';
        $this->addArgument(
            'which',
            InputArgument::REQUIRED,
            description: "'admin' to update $admin only, or 'others' to update all modules except for $admin",
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Client $githubClient */
        $githubClient = $this->container->get('Github\Client');
        // todo check we're authenticated at this point
        $githubService = new GitHubService($githubClient);
        // $githubService->test();die;

        $this->output = $output;
        $this->loadEnv();
        // Validate input arg
        $which = $input->getArgument('which');
        if (!in_array($which, ['admin', 'others'])) {
            throw new InvalidArgumentException('Argument must be "admin" or "others"');
        }
        // Set which modules will be updated based on input arg
        $modules = [];
        $githubs = [];
        if ($which === 'admin') {
            $modules = ['silverstripe/admin'];
            $githubs = ['silverstripe-admin'];
        } else {
            $modules = array_filter(
                (new ModuleService)->getSupportedJsModules('packagist'),
                fn($m) => $m !== 'silverstripe/admin'
            );
            $githubs = array_filter(
                (new ModuleService)->getSupportedJsModules('github'),
                fn($m) => $m !== 'silverstripe/silverstripe-admin'
            );
        }
        // Ensure the silverstripe/admin PR is green in CI before updating JS in other modules
        if ($which == 'others') {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "<question>Is the silverstripe/admin PR green in CI?</question> [y/n]</question>",
                false
            );
            if (!$helper->ask($input, $output, $question)) {
                $message = "The silverstripe/admin PR must be green in CI before updating other modules";
                $output->writeln("<error>$message</error>");
                return Command::SUCCESS;
            }
        }
        // Validate all branches
        foreach ($modules as $module) {
            $cwd = $this->getCwd($module);

            // temp hack
            if ($module == 'silverstripe/campaign-admin') {
                continue;
            }

            $currentBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
            if (!preg_match('#^\d(\.\d)?$#', $currentBranch)) {
                throw new RuntimeException("Starting branch for $cwd is $currentBranch, it must be either `x` or `x.y`");
            }
        }
        // Update module JS
        $homeDir = $this->getHomeDir();
        for ($i = 0; $i < count($modules); $i++) {
            $module = $modules[$i];
            $github = $githubs[$i];
            $repoName = explode('/', $github)[1];
            $output->writeln("<info>Updating $module</info>");
            // remove yarn.lock if it exists
            $cwd = $this->getCwd($module);
            // validate git branch
            $currentBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
            $this->runCommand('if [ -f yarn.lock ]; then rm yarn.lock; fi', $cwd);
            // run yarn build
            $command = implode(' && ', [
                'export NVM_DIR=' . $homeDir . '/.nvm',
                '. $NVM_DIR/nvm.sh',
                'nvm use',
                'yarn build'
            ]);
            $this->runCommand($command, $cwd);
            // git
            $currentBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
            $time = time();
            $newBranch = "pulls/$currentBranch/update-js-$time";
            $this->runCommand("git checkout -b $newBranch", $cwd);
            $this->runCommand('git add .', $cwd);
            $this->runCommand('git commit -m "DEP Update JS dependencies"', $cwd);
            $tempOrigin = "ccs-temp-$time'";
            $this->runCommand("git remote add $tempOrigin git@github.com:creative-commoners/$repoName", $cwd);
            $this->runCommand("git push $tempOrigin $newBranch --set-upstream", $cwd);
            $this->runCommand("git remote remove $tempOrigin", $cwd);
            // create pr via api
        }
        return Command::SUCCESS;
    }

    private function loadEnv(): void
    {
        $baseDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
        $dotenv = new Dotenv;
        $dotenv->load("$baseDir/.env");
        // I don't know the correct way to get dotenv to allow passing in GITHUB_TOKEN=<token> vendor/bin/update-js
        $dotenv->populate(['GITHUB_TOKEN' => getenv('GITHUB_TOKEN')], true);
        if (empty($_ENV['GITHUB_TOKEN'])) {
            $this->output->writeln('<error>env var GITHUB_TOKEN is missing</error>');
            exit(1);
        }
    }

    private function getCwd(string $module)
    {
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        return "$vendorDir/$module";
    }

    /**
     * Run a command and output its results
     */
    private function runCommand(string $command, string $cwd, bool $writeOut = true): string
    {
        $result = '';
        if ($writeOut) {
            $this->output->writeln("<comment>$command</comment>");
        }
        $process = Process::fromShellCommandline($command, $cwd);
        $process->run(function (string $type, string $buffer) use ($writeOut, &$result) {
            if ($writeOut) {
                $this->output->write($buffer);
            }
            $result .= $buffer;
        });
        $code = $process->getExitCode();
        if ($code !== 0) {
            throw new RuntimeException("Process returned exit code $code");
        }
        return trim($result);
    }

    /**
     * Get the users home dir, only works on unix-like systems (Linux, macOS)
     */
    private function getHomeDir(): ?string
    {
        $home = getenv('HOME');
        if (empty($home)) {
            throw new RuntimeException('Could not get HOME dir');
        }
        return $home;
    }
}

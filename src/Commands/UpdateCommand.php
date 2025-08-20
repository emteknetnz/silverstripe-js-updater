<?php

namespace emteknetnz\JsUpdater\Commands;

use emteknetnz\JsUpdater\Services\GitHubService;
use InvalidArgumentException;
use RuntimeException;
use emteknetnz\JsUpdater\Services\ModuleService;
use Intervention\Image\Interfaces\InputHandlerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'update',
    description: 'Update supported modules in current project',
)]
class UpdateCommand extends BaseCommand
{
    private InputInterface $input;
    private OutputInterface $output;
    private array $processedModules = [];
    private array $prUrls = [];

    protected function configure(): void
    {
        $admin = 'silverstripe/admin';
        $this->addArgument(
            'which',
            InputArgument::REQUIRED,
            "'admin' to update $admin only, or 'others' to update all modules except for $admin",
        );
        $this->addArgument(
            'githubIssueUrl',
            InputArgument::REQUIRED,
            'GitHub url of the parent issue',
        );
        $this->addOption(
            'only',
            'o',
            InputArgument::OPTIONAL,
            'Comma seperated list of repos to only run on e.g. silverstripe-elemental,silverstipe-asset-admin',
        );
        $this->addOption(
            'exclude',
            'e',
            InputArgument::OPTIONAL,
            'Comma seperated list of repos to exclude e.g. silverstripe-elemental,silverstipe-asset-admin',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->validateInputs($input);
        $which = $input->getArgument('which');
        $githubIssueUrl = $input->getArgument('githubIssueUrl');
        $homeDir = $this->getHomeDir();
        $modules = $this->getModules($input);
        $this->validateBranches($modules);
        $moduleService = $this->container->get(ModuleService::class);
        // Set which modules will be updated based on input arg
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
        try {
            // Update module JS
            foreach ($modules as $module) {
                $cwd = $this->getCwd($module);
                $ghrepo = $moduleService->getGitHubFromModule($module);
                $repoName = explode('/', $ghrepo)[1];
                $baseBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
                $output->writeln("<info>Updating $module</info>");
                // remove yarn.lock if it exists
                $this->runCommand('if [ -f yarn.lock ]; then rm yarn.lock; fi', $cwd);
                // run yarn build
                $command = implode(' && ', [
                    'export NVM_DIR=' . $homeDir . '/.nvm',
                    '. $NVM_DIR/nvm.sh',
                    'nvm use',
                    'yarn build'
                ]);
                $this->runCommand($command, $cwd);
                // git operations
                $baseBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
                $time = time();
                $headBranch = "pulls/$baseBranch/update-js-$time";
                // todo: this should be done BEFORE updating JS, in-case of accidental push on broken something
                $this->runCommand("git checkout -b $headBranch", $cwd);
                $this->runCommand('git add .', $cwd);
                $this->runCommand('git commit -m "DEP Update JS dependencies"', $cwd);
                $tempOrigin = "ccs-temp-$time";
                $this->runCommand("git remote add $tempOrigin git@github.com:creative-commoners/$repoName", $cwd);
                $this->runCommand("git push $tempOrigin $headBranch --set-upstream", $cwd);
                $this->runCommand("git remote remove $tempOrigin", $cwd);
                // create pr via github api
                $result = $this->container->get(GitHubService::class)->createPullRequest(
                    $ghrepo,
                    $githubIssueUrl,
                    $headBranch,
                    $baseBranch,
                    $output,
                );
                $this->processedModules[] = $module;
                $this->prUrls[] = $result['url'];
            }
        } finally {
            $output->writeln('<info>The following modules has PRs created (add to --exclude if running again):</info>');
            $processed = implode(',', $this->processedModules);
            $output->writeln("$processed");
            $output->writeln('<info>Created the following PRs:</info>');
            foreach ($this->prUrls as $url) {
                $output->writeln("- $url");
            }
        }
        return Command::SUCCESS;
    }

    private function validateBranches(array $modules): void
    {
        // todo: validate that if 'others', that admin branchs in pulls/3/... 
        foreach ($modules as $module) {
            $cwd = $this->getCwd($module);
            $baseBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
            if (!preg_match('#^\d(\.\d)?$#', $baseBranch)) {
                throw new RuntimeException("Starting branch for $cwd is $baseBranch, it must be either `x` or `x.y`");
            }
        }
    }

    private function validateInputs(): void
    {
        $which = $this->input->getArgument('which');
        if (!in_array($which, ['admin', 'others'])) {
            throw new InvalidArgumentException('Argument `which` must be "admin" or "others"');
        }
        $githubIssueUrl = $this->input->getArgument('githubIssueUrl');
        $rx = '#^https://github.com/([a-zA-Z0-9_\-]+)/([a-zA-Z0-9_\-\.]+)/issues/(\d+)$#';
        if (!preg_match($rx, $githubIssueUrl)) {
            throw new InvalidArgumentException('Argument `githubIssueUrl` is not a valid github issue url');
        }
    }

    /**
     * Get an array of modules aka packagist identifiers e.g. silverstripe/asset-admin
     */
    private function getModules(): array
    {
        $which = $this->input->getArgument('which');
        if ($which === 'admin') {
            return ['silverstripe/admin'];
        }
        $only = array_filter(explode(',', $this->input->getOption('only') ?: ''));
        $exclude = array_filter(explode(',', $this->input->getOption('exclude') ?: ''));
        /** @var ModuleService $moduleService */
        $moduleService = $this->container->get(ModuleService::class);
        return array_filter(
            $moduleService->getSupportedJsModules('packagist'),
            function (string $module) use ($moduleService, $only, $exclude) {
                if ($module === 'siverstripe/admin') {
                    return false;
                }
                $ghrepo = $moduleService->getGitHubFromModule($module);
                $repoName = explode('/', $ghrepo)[1];
                if (!empty($only) && !in_array($module, $only) && !in_array($ghrepo, $only) && !in_array($repoName, $only)) {
                    return false;
                }
                if (!empty($exclude) && (in_array($module, $exclude) || in_array($ghrepo, $exclude) || in_array($repoName, $exclude))) {
                    return false;
                }
                return true;
            }
        );
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
        $process->setTimeout(180);
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

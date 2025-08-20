<?php

namespace emteknetnz\JsUpdater\Commands;

use InvalidArgumentException;
use RuntimeException;
use emteknetnz\JsUpdater\Services\GitHubService;
use emteknetnz\JsUpdater\Services\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'update',
    description: 'Update supported modules in current project',
)]
class UpdateCommand extends BaseCommand
{
    /**
     * An instance of InputInterface
     */
    private InputInterface $input;

    /**
     * An instance of OutputInterface
     */
    private OutputInterface $output;

    /**
     * A list of modules that have been processed
     */
    private array $processedModules = [];

    /**
     * A list of pull request URLs that have been created
     */
    private array $pullRequestUrls = [];

    /**
     * Defines the command arguments and options.
     */
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
            'Comma-separated list of repos to only run on, e.g., silverstripe-elemental,silverstripe-asset-admin',
        );
        $this->addOption(
            'exclude',
            'e',
            InputArgument::OPTIONAL,
            'Comma-separated list of repos to exclude, e.g., silverstripe-elemental,silverstripe-asset-admin',
        );
        $this->addOption(
            'dry-run',
            'd',
            InputArgument::OPTIONAL,
            'Do not create pull requests',
        );
    }

    /**
     * Updates JavaScript dependencies, runs a build, and creates a pull request.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->validateEnv();
        $this->validateInputs($input);
        $which = $input->getArgument('which');
        $dryRun = $input->getOption('dry-run');
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
                // Checkout new git branch before making any changes
                $baseBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
                $time = time();
                $headBranch = "pulls/$baseBranch/update-js-$time";
                $this->runCommand("git checkout -b $headBranch", $cwd);
                // Remove yarn.lock if it exists
                $this->runCommand('if [ -f yarn.lock ]; then rm yarn.lock; fi', $cwd);
                // Run `yarn build`
                $command = implode(' && ', [
                    'export NVM_DIR=' . $homeDir . '/.nvm',
                    '. $NVM_DIR/nvm.sh',
                    'nvm use',
                    'yarn build'
                ]);
                $this->runCommand($command, $cwd);
                if ($dryRun) {
                    $output->writeln('Not creating pull-request because using --dry-run option');
                } else {
                    // Git operations
                    $baseBranch = $this->runCommand('git rev-parse --abbrev-ref HEAD', $cwd, false);
                    $this->runCommand('git add .', $cwd);
                    $this->runCommand('git commit -m "DEP Update JS dependencies"', $cwd);
                    $tempOrigin = "ccs-temp-$time";
                    $this->runCommand("git remote add $tempOrigin git@github.com:creative-commoners/$repoName", $cwd);
                    $this->runCommand("git push $tempOrigin $headBranch --set-upstream", $cwd);
                    $this->runCommand("git remote remove $tempOrigin", $cwd);
                    // Create pull-requset via github api
                    $result = $this->container->get(GitHubService::class)->createPullRequest(
                        $ghrepo,
                        $githubIssueUrl,
                        $headBranch,
                        $baseBranch,
                        $output,
                    );
                    $this->pullRequestUrls[] = $result['html_url'];
                }
                $this->processedModules[] = $module;
            }
        } finally {
            $output->writeln('<info>The following modules has PRs created (add to --exclude if running again):</info>');
            $processed = implode(',', $this->processedModules);
            $output->writeln("$processed");
            if (!$dryRun) {
                $output->writeln('<info>Created the following PRs:</info>');
                foreach ($this->pullRequestUrls as $url) {
                    $output->writeln("- $url");
                }
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Gets the working directory for a given module.
     */
    private function getCwd(string $module)
    {
        $vendorDir = dirname(dirname(dirname(dirname(__DIR__))));
        return "$vendorDir/$module";
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
                if ($module === 'silverstripe/admin') {
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
     * Validates that the current branch in each module directory is a valid release branch.
     */
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

    /**
     * Validates that required env variables are valid
     */
    private function validateEnv(): void
    {
        if (empty($_ENV['GITHUB_TOKEN'])) {
            throw new InvalidArgumentException('env var GITHUB_TOKEN is missing');
        }
    }

    /**
     * Validates the 'which' and 'githubIssueUrl' arguments.
     */
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
}

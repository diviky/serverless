<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless\Serverless;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SlsCommand extends VaporDeployCommand
{
    use ExecuteTrait;

    protected function configure()
    {
        $this
            ->setName('sls:deploy')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name')
            ->addOption('commit', null, InputOption::VALUE_OPTIONAL, 'The commit hash that is being deployed')
            ->addOption('message', null, InputOption::VALUE_OPTIONAL, 'The message for the commit that is being deployed')
            ->addOption('without-waiting', null, InputOption::VALUE_NONE, 'Deploy without waiting for progress')
            ->addOption('fresh-assets', null, InputOption::VALUE_NONE, 'Upload a fresh copy of all assets')
            ->addOption('build-arg', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build argument')
            ->addOption('build-option', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build option')
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'Deploy with debug mode enabled', 'unset')
            ->setDescription('Deploy an environment');
    }

    protected function uploadArtifact($environment, $uuid)
    {
        Helpers::line();

        Serverless::generate($environment);
        Serverless::deploy();

        return [];
    }

    /**
     * Serve the artifact's assets at the given path.
     *
     * @return void
     */
    protected function serveAssets(array $artifact) {}
}

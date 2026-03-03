<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless\Serverless;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SlsCommand extends DeployCommand
{
    use ExecuteTrait;

    protected function configure()
    {
        $this
            ->setName('sls:deploy')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'production')
            ->addOption('commit', null, InputOption::VALUE_OPTIONAL, 'The commit hash that is being deployed')
            ->addOption('message', null, InputOption::VALUE_OPTIONAL, 'The message for the commit that is being deployed')
            ->addOption('without-waiting', null, InputOption::VALUE_NONE, 'Deploy without waiting for progress')
            ->addOption('fresh-assets', null, InputOption::VALUE_NONE, 'Upload a fresh copy of all assets')
            ->addOption('build-arg', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build argument')
            ->addOption('build-option', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build option')
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'Deploy with debug mode enabled', 'unset')
            ->addOption('docker-build', null, InputOption::VALUE_NEGATABLE, 'Docker build command', true)
            ->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'AWS profile', null)
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'AWS region', null)
            ->setDescription('Deploy an environment');
    }

    protected function uploadArtifact($environment, $uuid)
    {
        $artifact = parent::uploadArtifact($environment, $uuid);

        Helpers::line();

        Serverless::generate($environment, $this->option('profile'), $this->option('region'));
        Serverless::deploy();

        return $artifact;
    }
}

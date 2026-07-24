<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ArtifactCommand extends DeployCommand
{
    use ExecuteTrait;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('artifact')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'production')
            ->addOption('commit', null, InputOption::VALUE_OPTIONAL, 'The commit hash that is being deployed')
            ->addOption('message', null, InputOption::VALUE_OPTIONAL, 'The message for the commit that is being deployed')
            ->addOption('without-waiting', null, InputOption::VALUE_NONE, 'Deploy without waiting for progress')
            ->addOption('fresh-assets', null, InputOption::VALUE_NONE, 'Upload a fresh copy of all assets')
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'Deploy with debug mode enabled', 'unset')
            ->addOption('docker-build', null, InputOption::VALUE_NEGATABLE, 'Docker build command', true)
            ->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'AWS profile', null)
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'AWS region', null)
            ->setDescription('Deploy an environment');
    }

    /**
     * Build the project and create a new artifact for the deployment.
     *
     * @return array
     */
    protected function buildProject(array $project)
    {
        $uuid = (string) time();

        return $this->uploadArtifact(
            $this->argument('environment'),
            $uuid
        );
    }
}

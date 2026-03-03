<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless\Serverless;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GenerateCommand extends VaporBuildCommand
{
    use ExecuteTrait;

    /**
     * Configure the command options.
     */
    protected function configure()
    {
        $this
            ->setName('sls:generate')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'production')
            ->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'AWS profile', null)
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'AWS region', null)
            ->setDescription('Create Serverless file');
    }

    /**
     * Execute the command.
     */
    public function handle()
    {
        Serverless::generate($this->argument('environment'), $this->option('profile'), $this->option('region'));

        Helpers::line();
        Helpers::line('<info>Serverless file created successfully.</info>');
    }
}

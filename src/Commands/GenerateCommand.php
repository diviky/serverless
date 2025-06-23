<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless\Serverless;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputArgument;

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
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'staging')
            ->setDescription('Create Serverless file');
    }

    /**
     * Execute the command.
     */
    public function handle()
    {
        Serverless::generate($this->argument('environment'));

        Helpers::line();
        Helpers::line('<info>Serverless file created successfully.</info>');
    }
}

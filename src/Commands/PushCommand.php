<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputArgument;

class PushCommand extends VaporBuildCommand
{
    use ExecuteTrait;

    /**
     * Execute the command.
     */
    public function handle()
    {
        Serverless::deploy($this->argument('args'));

        Helpers::line();
        Helpers::line('<info>Serverless file created successfully.</info>');
    }

    /**
     * Configure the command options.
     */
    protected function configure()
    {
        $this
            ->setName('push')
            ->addArgument('args', InputArgument::OPTIONAL, 'Extra arguments for sls')
            ->setDescription('Deploy the Serverless file');
    }
}

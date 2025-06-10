<?php

namespace Diviky\Serverless\Commands;

use DateTime;
use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless\Serverless;
use Illuminate\Filesystem\Filesystem;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Path;
use Symfony\Component\Console\Input\InputArgument;

class PushCommand extends VaporBuildCommand
{
    use ExecuteTrait;

    /**
     * Execute the command.
     */
    public function handle()
    {
        $startedAt = new DateTime;

        Serverless::deploy($this->argument('args'));

        (new Filesystem)->deleteDirectory(Path::vapor());

        $time = (new DateTime)->diff($startedAt)->format('%im%Ss');

        Helpers::line();
        Helpers::line('<info>Project deployed successfully.</info> (' . $time . ')');
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

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\InitCommand as VaporInitCommand;
use Laravel\VaporCli\Helpers;

class InitCommand extends VaporInitCommand
{
    use ExecuteTrait;

    /**
     * Execute the command.
     */
    public function handle()
    {
        parent::handle();

        if (Helpers::confirm('Would you like to install the serverless package')) {
            \passthru('npm install -g serverless');
            \passthru('npm install -g serverless-deployment-bucket');
            \passthru('npm install -g serverless-dynamodb-autoscaling');
        }
    }
}

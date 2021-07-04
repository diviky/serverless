<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;

class DeployCommand extends VaporDeployCommand
{
    use ExecuteTrait;
}

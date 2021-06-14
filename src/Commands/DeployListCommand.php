<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DeployListCommand as VaporDeployListCommand;

class DeployListCommand extends VaporDeployListCommand
{
    use ExecuteTrait;
}

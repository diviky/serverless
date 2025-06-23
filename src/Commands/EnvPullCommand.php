<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\Commands\EnvPullCommand as VaporEnvPullCommand;

class EnvPullCommand extends VaporEnvPullCommand
{
    use ExecuteTrait;
}

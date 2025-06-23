<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\Commands\EnvPushCommand as VaporEnvPushCommand;

class EnvPushCommand extends VaporEnvPushCommand
{
    use ExecuteTrait;
}

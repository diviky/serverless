<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\Commands\EnvDeleteCommand as VaporEnvDeleteCommand;

class EnvDeleteCommand extends VaporEnvDeleteCommand
{
    use ExecuteTrait;
}

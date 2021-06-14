<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvDeleteCommand as VaporEnvDeleteCommand;

class EnvDeleteCommand extends VaporEnvDeleteCommand
{
    use ExecuteTrait;
}

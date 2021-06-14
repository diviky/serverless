<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvPushCommand as VaporEnvPushCommand;

class EnvPushCommand extends VaporEnvPushCommand
{
    use ExecuteTrait;
}

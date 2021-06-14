<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvPullCommand as VaporEnvPullCommand;

class EnvPullCommand extends VaporEnvPullCommand
{
    use ExecuteTrait;
}

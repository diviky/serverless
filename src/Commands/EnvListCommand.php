<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvListCommand as VaporEnvListCommand;

class EnvListCommand extends VaporEnvListCommand
{
    use ExecuteTrait;
}

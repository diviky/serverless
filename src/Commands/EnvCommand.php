<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvCommand as VaporEnvCommand;

class EnvCommand extends VaporEnvCommand
{
    use ExecuteTrait;
}

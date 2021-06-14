<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvCloneCommand as VaporEnvCloneCommand;

class EnvCloneCommand extends VaporEnvCloneCommand
{
    use ExecuteTrait;
}

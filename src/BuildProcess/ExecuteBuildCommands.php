<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\BuildProcess\ExecuteBuildCommands as BaseExecuteBuildCommands;

class ExecuteBuildCommands extends BaseExecuteBuildCommands
{
    /**
     * @var array<int, string>
     */
    protected $unsupportedCommands = [
        'clear-compiled',
        'route:cache',
    ];
}

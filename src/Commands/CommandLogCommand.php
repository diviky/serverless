<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CommandLogCommand as VaporCommandLogCommand;

class CommandLogCommand extends VaporCommandLogCommand
{
    use ExecuteTrait;
}

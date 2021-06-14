<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CommandCommand as VaporCommandCommand;

class CommandCommand extends VaporCommandCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\RollbackCommand as VaporRollbackCommand;

class RollbackCommand extends VaporRollbackCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\JumpDeleteCommand as VaporJumpDeleteCommand;

class JumpDeleteCommand extends VaporJumpDeleteCommand
{
    use ExecuteTrait;
}

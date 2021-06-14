<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\TailCommand as VaporTailCommand;

class TailCommand extends VaporTailCommand
{
    use ExecuteTrait;
}

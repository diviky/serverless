<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\HookLogCommand as VaporHookLogCommand;

class HookLogCommand extends VaporHookLogCommand
{
    use ExecuteTrait;
}

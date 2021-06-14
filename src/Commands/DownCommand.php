<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DownCommand as VaporDownCommand;

class DownCommand extends VaporDownCommand
{
    use ExecuteTrait;
}

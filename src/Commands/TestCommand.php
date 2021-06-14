<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\TestCommand as VaporTestCommand;

class TestCommand extends VaporTestCommand
{
    use ExecuteTrait;
}

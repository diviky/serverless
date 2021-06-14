<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseShellCommand as VaporDatabaseShellCommand;

class DatabaseShellCommand extends VaporDatabaseShellCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseCommand as VaporDatabaseCommand;

class DatabaseCommand extends VaporDatabaseCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseListCommand as VaporDatabaseListCommand;

class DatabaseListCommand extends VaporDatabaseListCommand
{
    use ExecuteTrait;
}

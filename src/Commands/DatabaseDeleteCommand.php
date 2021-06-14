<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseDeleteCommand as VaporDatabaseDeleteCommand;

class DatabaseDeleteCommand extends VaporDatabaseDeleteCommand
{
    use ExecuteTrait;
}

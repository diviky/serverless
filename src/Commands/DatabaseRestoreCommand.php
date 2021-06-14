<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseRestoreCommand as VaporDatabaseRestoreCommand;

class DatabaseRestoreCommand extends VaporDatabaseRestoreCommand
{
    use ExecuteTrait;
}

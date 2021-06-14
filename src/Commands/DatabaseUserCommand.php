<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseUserCommand as VaporDatabaseUserCommand;

class DatabaseUserCommand extends VaporDatabaseUserCommand
{
    use ExecuteTrait;
}

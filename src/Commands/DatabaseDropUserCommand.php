<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseDropUserCommand as VaporDatabaseDropUserCommand;

class DatabaseDropUserCommand extends VaporDatabaseDropUserCommand
{
    use ExecuteTrait;
}

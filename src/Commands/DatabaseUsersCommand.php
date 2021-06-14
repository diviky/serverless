<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseUsersCommand as VaporDatabaseUsersCommand;

class DatabaseUsersCommand extends VaporDatabaseUsersCommand
{
    use ExecuteTrait;
}

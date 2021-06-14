<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseShowCommand as VaporDatabaseShowCommand;

class DatabaseShowCommand extends VaporDatabaseShowCommand
{
    use ExecuteTrait;
}

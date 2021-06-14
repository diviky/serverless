<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseScaleCommand as VaporDatabaseScaleCommand;

class DatabaseScaleCommand extends VaporDatabaseScaleCommand
{
    use ExecuteTrait;
}

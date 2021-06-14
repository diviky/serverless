<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabasePasswordCommand as VaporDatabasePasswordCommand;

class DatabasePasswordCommand extends VaporDatabasePasswordCommand
{
    use ExecuteTrait;
}

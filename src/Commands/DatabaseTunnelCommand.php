<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseTunnelCommand as VaporDatabaseTunnelCommand;

class DatabaseTunnelCommand extends VaporDatabaseTunnelCommand
{
    use ExecuteTrait;
}

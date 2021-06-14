<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheTunnelCommand as VaporCacheTunnelCommand;

class CacheTunnelCommand extends VaporCacheTunnelCommand
{
    use ExecuteTrait;
}

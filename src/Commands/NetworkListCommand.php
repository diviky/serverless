<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\NetworkListCommand as VaporNetworkListCommand;

class NetworkListCommand extends VaporNetworkListCommand
{
    use ExecuteTrait;
}

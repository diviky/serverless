<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\NetworkDeleteNatCommand as VaporNetworkDeleteNatCommand;

class NetworkDeleteNatCommand extends VaporNetworkDeleteNatCommand
{
    use ExecuteTrait;
}

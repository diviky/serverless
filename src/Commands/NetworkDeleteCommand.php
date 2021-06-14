<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\NetworkDeleteCommand as VaporNetworkDeleteCommand;

class NetworkDeleteCommand extends VaporNetworkDeleteCommand
{
    use ExecuteTrait;
}

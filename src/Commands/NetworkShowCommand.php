<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\NetworkShowCommand as VaporNetworkShowCommand;

class NetworkShowCommand extends VaporNetworkShowCommand
{
    use ExecuteTrait;
}

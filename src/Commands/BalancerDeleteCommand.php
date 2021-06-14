<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\BalancerDeleteCommand as VaporBalancerDeleteCommand;

class BalancerDeleteCommand extends VaporBalancerDeleteCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\BalancerListCommand as VaporBalancerListCommand;

class BalancerListCommand extends VaporBalancerListCommand
{
    use ExecuteTrait;
}

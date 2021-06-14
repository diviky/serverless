<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\BalancerCommand as VaporBalancerCommand;

class BalancerCommand extends VaporBalancerCommand
{
    use ExecuteTrait;
}

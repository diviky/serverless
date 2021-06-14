<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\RedeployCommand as VaporRedeployCommand;

class RedeployCommand extends VaporRedeployCommand
{
    use ExecuteTrait;
}

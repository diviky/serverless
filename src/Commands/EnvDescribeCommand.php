<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\EnvDescribeCommand as VaporEnvDescribeCommand;

class EnvDescribeCommand extends VaporEnvDescribeCommand
{
    use ExecuteTrait;
}

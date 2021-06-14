<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\MetricsCommand as VaporMetricsCommand;

class MetricsCommand extends VaporMetricsCommand
{
    use ExecuteTrait;
}

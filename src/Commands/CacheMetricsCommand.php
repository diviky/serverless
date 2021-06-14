<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheMetricsCommand as VaporCacheMetricsCommand;

class CacheMetricsCommand extends VaporCacheMetricsCommand
{
    use ExecuteTrait;
}

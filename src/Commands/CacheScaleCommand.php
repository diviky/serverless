<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheScaleCommand as VaporCacheScaleCommand;

class CacheScaleCommand extends VaporCacheScaleCommand
{
    use ExecuteTrait;
}

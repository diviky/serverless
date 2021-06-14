<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheCommand as VaporCacheCommand;

class CacheCommand extends VaporCacheCommand
{
    use ExecuteTrait;
}

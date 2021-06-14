<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheListCommand as VaporCacheListCommand;

class CacheListCommand extends VaporCacheListCommand
{
    use ExecuteTrait;
}

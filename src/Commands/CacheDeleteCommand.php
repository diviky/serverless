<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheDeleteCommand as VaporCacheDeleteCommand;

class CacheDeleteCommand extends VaporCacheDeleteCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CacheShowCommand as VaporCacheShowCommand;

class CacheShowCommand extends VaporCacheShowCommand
{
    use ExecuteTrait;
}

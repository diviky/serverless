<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\ZoneDeleteCommand as VaporZoneDeleteCommand;

class ZoneDeleteCommand extends VaporZoneDeleteCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\RecordDeleteCommand as VaporRecordDeleteCommand;

class RecordDeleteCommand extends VaporRecordDeleteCommand
{
    use ExecuteTrait;
}

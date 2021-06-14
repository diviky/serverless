<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\DatabaseMetricsCommand as VaporDatabaseMetricsCommand;

class DatabaseMetricsCommand extends VaporDatabaseMetricsCommand
{
    use ExecuteTrait;
}

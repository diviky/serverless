<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\ProjectDescribeCommand as VaporProjectDescribeCommand;

class ProjectDescribeCommand extends VaporProjectDescribeCommand
{
    use ExecuteTrait;
}

<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\LoginCommand as VaporLoginCommand;

class LoginCommand extends VaporLoginCommand
{
    use ExecuteTrait;
}

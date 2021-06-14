<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\SecretListCommand as VaporSecretListCommand;

class SecretListCommand extends VaporSecretListCommand
{
    use ExecuteTrait;
}

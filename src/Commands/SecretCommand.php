<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\SecretCommand as VaporSecretCommand;

class SecretCommand extends VaporSecretCommand
{
    use ExecuteTrait;
}

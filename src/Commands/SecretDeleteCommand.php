<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\SecretDeleteCommand as VaporSecretDeleteCommand;

class SecretDeleteCommand extends VaporSecretDeleteCommand
{
    use ExecuteTrait;
}

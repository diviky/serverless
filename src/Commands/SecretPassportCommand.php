<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\SecretPassportCommand as VaporSecretPassportCommand;

class SecretPassportCommand extends VaporSecretPassportCommand
{
    use ExecuteTrait;
}

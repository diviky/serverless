<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\ProviderDeleteCommand as VaporProviderDeleteCommand;

class ProviderDeleteCommand extends VaporProviderDeleteCommand
{
    use ExecuteTrait;
}

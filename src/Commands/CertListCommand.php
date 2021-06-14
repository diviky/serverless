<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CertListCommand as VaporCertListCommand;

class CertListCommand extends VaporCertListCommand
{
    use ExecuteTrait;
}

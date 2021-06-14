<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CertDeleteCommand as VaporCertDeleteCommand;

class CertDeleteCommand extends VaporCertDeleteCommand
{
    use ExecuteTrait;
}

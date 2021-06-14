<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\CertValidateCommand as VaporCertValidateCommand;

class CertValidateCommand extends VaporCertValidateCommand
{
    use ExecuteTrait;
}

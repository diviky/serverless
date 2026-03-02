<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Concerns\EnvReader;
use Laravel\VaporCli\BuildProcess\ParticipatesInBuildProcess;
use Laravel\VaporCli\Helpers;

class CollectAndEncryptEnv
{
    use EnvReader;
    use ParticipatesInBuildProcess;

    /**
     * Execute the build process step.
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Collecting and encrypting environment variables</>');

        return true;
    }
}

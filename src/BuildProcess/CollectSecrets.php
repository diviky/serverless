<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Concerns\EnvReader;
use Laravel\VaporCli\BuildProcess\ParticipatesInBuildProcess;
use Laravel\VaporCli\Helpers;

class CollectSecrets
{
    use EnvReader;
    use ParticipatesInBuildProcess;

    /**
     * Execute the build process step.
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Collecting Secrets</>');

        $secrets = static::getProjectEnv($this->appPath, $this->environment, '.secret');

        $this->files->put(
            $this->appPath.'/vaporSecrets.php',
            '<?php return '.\var_export($secrets, true).';'
        );

        return $secrets;
    }
}

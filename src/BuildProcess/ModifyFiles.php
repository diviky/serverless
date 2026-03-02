<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\BuildProcess\ParticipatesInBuildProcess;
use Laravel\VaporCli\Helpers;

class ModifyFiles
{
    use ParticipatesInBuildProcess;

    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Modifying Files</>');

        file_put_contents(
            $this->appPath . '/vendor/laravel/vapor-core/src/Runtime/Environment.php',
            $this->configure($this->appPath . '/vendor/laravel/vapor-core/src/Runtime/Environment.php')
        );
    }

    /**
     * Configure the Artisan executable.
     *
     * @param  string  $file
     * @return string
     */
    protected function configure($file)
    {
        return str_replace(
            [
                '$this->app->basePath($this->encryptedFile),',
            ],
            [
                '$_ENV[\'ENV_ENCRYPTED_FILE_PATH\'] ?? $this->app->basePath($this->encryptedFile),',
            ],
            file_get_contents($file)
        );
    }
}

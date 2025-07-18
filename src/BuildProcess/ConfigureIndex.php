<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\Helpers;

class ConfigureIndex extends ConfigureArtisan
{
    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Configuring Index</>');

        file_put_contents(
            $this->appPath.'/public/index.php',
            $this->configure($this->appPath.'/public/index.php')
        );
    }
}

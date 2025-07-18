<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Manifest;
use Diviky\Serverless\Package;
use Laravel\VaporCli\BuildProcess\ParticipatesInBuildProcess;

class PackageApplication
{
    use ParticipatesInBuildProcess;

    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        if (!Manifest::shouldPackageApplication($this->environment)) {
            return;
        }

        $config = Manifest::environment($this->environment)['package'] ?? [];

        $package = new Package($this->appPath);

        if (isset($config['obfuscate']) && isset($config['obfuscate']['enabled']) && $config['obfuscate']['enabled'] === true) {
            $package->obfuscate($config['obfuscate']);
        }

        if (isset($config['phar']) && $config['phar'] === true) {
            $package->createPharFile();
        }
    }
}

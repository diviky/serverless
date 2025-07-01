<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Manifest;
use Laravel\VaporCli\BuildProcess\ParticipatesInBuildProcess;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Process\Process;

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

        $this->createPharFile();
    }

    protected function createPharFile()
    {
        $this->writeBoxConfigFile();

        $command = 'box compile';

        $process = Process::fromShellCommandline($command, $this->appPath);
        $process->setTimeout(null);

        $process->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    protected function writeBoxConfigFile()
    {
        if ($this->files->exists($this->appPath . '/box.json')) {
            return;
        }

        $config = [
            'main' => 'artisan',
            'output' => 'build/app.phar',
            'alias' => 'app.phar',
            'stub' => true,
            'compactors' => [
                'KevinGH\\Box\\Compactor\\Php',
            ],
            'dump-autoload' => false,
            'exclude-composer-files' => false,
            'banner' => false,
            'shebang' => false,
            'compression' => 'GZ',
            'algorithm' => 'SHA512',
            'directories' => [
                '.',
            ],
        ];

        $this->files->put($this->appPath . '/box.json', json_encode($config, JSON_PRETTY_PRINT));
    }
}

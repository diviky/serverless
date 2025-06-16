<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;
use Laravel\VaporCli\Path;

class DeployCommand extends VaporDeployCommand
{
    use ExecuteTrait;

    /**
     * Build the project and create a new artifact for the deployment.
     *
     * @return array
     */
    protected function buildProject(array $project)
    {
        $uuid = (string) time();

        $this->call('build', [
            'environment' => $this->argument('environment'),
            '--asset-url' => $this->assetDomain($project) . '/' . $uuid,
            '--manifest' => Path::manifest(),
            '--build-arg' => $this->option('build-arg'),
            '--build-option' => $this->option('build-option'),
        ]);

        return $this->uploadArtifact(
            $this->argument('environment'),
            $uuid
        );
    }
}

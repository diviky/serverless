<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\ServeAssets;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;
use Laravel\VaporCli\Helpers;
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

    /**
     * Serve the artifact's assets at the given path.
     *
     * @return void
     */
    protected function serveAssets(array $artifact)
    {
        Helpers::line();

        (new ServeAssets)->__invoke($this->vapor, $artifact, $this->option('fresh-assets'));
    }
}

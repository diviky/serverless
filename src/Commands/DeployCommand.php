<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Serverless;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;
use Laravel\VaporCli\Helpers;

class DeployCommand extends VaporDeployCommand
{
    use ExecuteTrait;

    protected function uploadArtifact($environment, $uuid)
    {
        Helpers::line();

        Serverless::generate($environment);
        Serverless::deploy();
    }

    /**
     * Serve the artifact's assets at the given path.
     *
     * @return void
     */
    protected function serveAssets(array $artifact) {}
}

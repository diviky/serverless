<?php

namespace Diviky\Serverless;

use Laravel\VaporCli\ConsoleVaporClient;

class ServeAssets extends \Laravel\VaporCli\ServeAssets
{
    protected function getAuthorizedAssetRequests(
        ConsoleVaporClient $vapor,
        array $artifact,
        array $assetFiles,
        bool $fresh
    ) {
        return $vapor->authorizeArtifactAssets($artifact, $assetFiles, $fresh);
    }
}

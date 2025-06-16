<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Manifest;
use Laravel\VaporCli\BuildProcess\ExtractAssetsToSeparateDirectory as BaseExtractAssetsToSeparateDirectory;

class ExtractAssetsToSeparateDirectory extends BaseExtractAssetsToSeparateDirectory
{
    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        if (!Manifest::shouldSeparateAssets($this->environment)) {
            return;
        }

        parent::__invoke();
    }
}

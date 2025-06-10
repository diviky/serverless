<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Manifest;
use Illuminate\Filesystem\Filesystem;
use Laravel\VaporCli\AssetFiles;
use Laravel\VaporCli\BuildProcess\ExtractAssetsToSeparateDirectory as BaseExtractAssetsToSeparateDirectory;
use Laravel\VaporCli\Helpers;

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

        Helpers::step('<options=bold>Extracting Assets</>');

        $this->ensureAssetDirectoryExists();

        (new Filesystem)->copyDirectory(
            $this->appPath . '/public',
            $this->buildPath . '/assets'
        );

        foreach (AssetFiles::get($this->appPath . '/public') as $file) {
            @unlink($file->getRealPath());
        }
    }

    /**
     * Ensure that the asset directory exists.
     *
     * @return void
     */
    protected function ensureAssetDirectoryExists()
    {
        if ($this->files->isDirectory($this->buildPath . '/assets')) {
            $this->files->deleteDirectory($this->buildPath . '/assets');
        }

        $this->files->makeDirectory(
            $this->buildPath . '/assets',
            0755,
            true
        );
    }
}

<?php

namespace Diviky\Serverless\BuildProcess;

use Diviky\Serverless\Docker;
use Diviky\Serverless\Manifest;
use Illuminate\Support\Collection;
use Laravel\VaporCli\BuildProcess\BuildContainerImage as BaseBuildContainerImage;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Path;

class BuildContainerImage extends BaseBuildContainerImage
{
    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        if (!Manifest::usesContainerImage($this->environment)) {
            return;
        }

        $buildArgs = Collection::make($this->manifestBuildArgs)
            ->merge(Collection::make($this->cliBuildArgs)
                ->mapWithKeys(function ($value) {
                    [$key, $value] = explode('=', $value, 2);

                    return [$key => $value];
                })
            )->toArray();

        if (!$this->validateDockerFile($this->environment, $runtime = Manifest::runtime($this->environment), $buildArgs)) {
            Helpers::abort('The base image used in ' . Path::dockerfile($this->environment) . ' is incompatible with the "' . $runtime . '" runtime, or you are running an outdated version of Vapor CLI.');
        }

        Helpers::step('<options=bold>Building Container Image</>');

        Docker::build(
            $this->appPath,
            Manifest::name(),
            $this->environment,
            $this->formatBuildArguments(),
            $this->cliBuildOptions
        );
    }
}

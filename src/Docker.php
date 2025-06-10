<?php

namespace Diviky\Serverless;

use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Symfony\Component\Process\Process;

class Docker extends \Laravel\VaporCli\Docker
{
    /**
     * Build a docker image.
     *
     * @param  string  $path
     * @param  string  $project
     * @param  string  $environment
     * @param  array  $cliBuildArgs
     * @param  array  $cliBuildOptions
     * @return void
     */
    public static function build($path, $project, $environment, $cliBuildArgs, $cliBuildOptions)
    {
        $buildCommand = static::buildCommand(
            $project,
            $environment,
            $cliBuildArgs,
            Manifest::dockerBuildArgs($environment),
            $cliBuildOptions,
            Manifest::dockerBuildOptions($environment)
        );

        $buildCommand = str_replace('--pull', '', $buildCommand);

        Helpers::line(sprintf('Build command: %s', $buildCommand));

        Process::fromShellCommandline(
            $buildCommand,
            $path
        )->setTimeout(null)->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }
}

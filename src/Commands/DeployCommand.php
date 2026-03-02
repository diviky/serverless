<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\ServeAssets;
use Laravel\VaporCli\Aws\AwsStorageProvider;
use Laravel\VaporCli\Commands\DeployCommand as VaporDeployCommand;
use Laravel\VaporCli\Docker;
use Laravel\VaporCli\Git;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DeployCommand extends VaporDeployCommand
{
    use ExecuteTrait;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('deploy')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name')
            ->addOption('commit', null, InputOption::VALUE_OPTIONAL, 'The commit hash that is being deployed')
            ->addOption('message', null, InputOption::VALUE_OPTIONAL, 'The message for the commit that is being deployed')
            ->addOption('without-waiting', null, InputOption::VALUE_NONE, 'Deploy without waiting for progress')
            ->addOption('fresh-assets', null, InputOption::VALUE_NONE, 'Upload a fresh copy of all assets')
            ->addOption('build-arg', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build argument')
            ->addOption('build-option', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build option')
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'Deploy with debug mode enabled', 'unset')
            ->addOption('docker-build', null, InputOption::VALUE_NEGATABLE, 'Docker build command', true)
            ->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'AWS profile', 'default')
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'AWS region', 'ap-south-1')
            ->setDescription('Build and Deploy an environment to cloud provider');
    }

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
            '--asset-url' => $this->assetDomain($project).'/'.$uuid,
            '--manifest' => Path::manifest(),
            '--build-arg' => $this->option('build-arg'),
            '--build-option' => $this->option('build-option'),
            '--docker-build' => $this->option('docker-build'),
            '--profile' => $this->option('profile'),
            '--region' => $this->option('region'),
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

    /**
     * Upload the deployment artifact.
     *
     * @param  string  $environment
     * @param  string  $uuid
     * @return array
     */
    protected function uploadArtifact($environment, $uuid)
    {
        Helpers::line();

        $usesContainerImage = Manifest::usesContainerImage($environment);
        $usesContainerImage = $usesContainerImage && $this->option('docker-build') !== false;

        if (! $usesContainerImage) {
            Helpers::step('<comment>Uploading Deployment Artifact</comment> ('.Helpers::megabytes(Path::artifact()).')');
        }

        $artifact = $this->vapor->createArtifact(
            Manifest::id(),
            $uuid,
            $environment,
            $usesContainerImage ? null : Path::artifact(),
            $this->option('commit') ?: Git::hash(),
            $this->option('message') ?: Git::message(),
            Manifest::shouldSeparateVendor($environment) ? $this->createVendorHash() : null,
            $this->getCliVersion(),
            $this->getCoreVersion()
        );

        if (isset($artifact['vendor_url'])) {
            Helpers::line();

            Helpers::step('<comment>Uploading Vendor Directory</comment> ('.Helpers::megabytes(Path::vendorArtifact()).')');

            Helpers::app(AwsStorageProvider::class)->store($artifact['vendor_url'], [], Path::vendorArtifact(), true);
        }

        if ($usesContainerImage) {
            Helpers::line();

            Helpers::step('<comment>Pushing Container Image</comment>');

            Docker::publish(
                Path::app(),
                Manifest::name(),
                $environment,
                $artifact['container_registry_token'],
                $artifact['container_repository'],
                $artifact['container_image_tag']);
        }

        return $artifact;
    }
}

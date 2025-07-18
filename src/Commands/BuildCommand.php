<?php

namespace Diviky\Serverless\Commands;

use DateTime;
use Diviky\Serverless\BuildProcess\BuildContainerImage;
use Diviky\Serverless\BuildProcess\CollectSecrets;
use Diviky\Serverless\BuildProcess\CompressApplication;
use Diviky\Serverless\BuildProcess\CopyApplicationToBuildPath;
use Diviky\Serverless\BuildProcess\ExecuteBuildCommands;
use Diviky\Serverless\BuildProcess\ExtractAssetsToSeparateDirectory;
use Diviky\Serverless\BuildProcess\PackageApplication;
use Diviky\Serverless\BuildProcess\RemoveIgnoredFiles;
use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\BuildProcess\CompressVendor;
use Diviky\Serverless\BuildProcess\ConfigureArtisan;
use Diviky\Serverless\BuildProcess\ConfigureIndex;
use Laravel\VaporCli\BuildProcess\ConfigureComposerAutoloader;
use Laravel\VaporCli\BuildProcess\ExtractVendorToSeparateDirectory;
use Laravel\VaporCli\BuildProcess\HarmonizeConfigurationFiles;
use Laravel\VaporCli\BuildProcess\InjectErrorPages;
use Laravel\VaporCli\BuildProcess\InjectHandlers;
use Laravel\VaporCli\BuildProcess\InjectRdsCertificate;
use Laravel\VaporCli\BuildProcess\ProcessAssets;
use Laravel\VaporCli\BuildProcess\RemovePintBinary;
use Laravel\VaporCli\BuildProcess\RemoveVendorPlatformCheck;
use Laravel\VaporCli\BuildProcess\SetBuildEnvironment;
use Laravel\VaporCli\BuildProcess\ValidateManifest;
use Laravel\VaporCli\BuildProcess\ValidateOctaneDependencies;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;

class BuildCommand extends VaporBuildCommand
{
    use ExecuteTrait;

    /**
     * Execute the command.
     */
    public function handle()
    {
        Helpers::ensure_api_token_is_available();

        Helpers::line('Building project...');

        if (Manifest::usesContainerImage($this->argument('environment')) &&
            !file_exists($file = Path::dockerfile($this->argument('environment')))) {
            Helpers::abort("Please create a Dockerfile at [$file].");
        }

        $startedAt = new DateTime;

        collect([
            new ValidateManifest($this->argument('environment')),
            new CopyApplicationToBuildPath,
            new HarmonizeConfigurationFiles,
            new SetBuildEnvironment($this->argument('environment'), $this->option('asset-url')),
            new ExecuteBuildCommands($this->argument('environment')),
            new ValidateOctaneDependencies($this->argument('environment')),
            new ConfigureArtisan($this->argument('environment')),
            new ConfigureIndex($this->argument('environment')),
            new ConfigureComposerAutoloader($this->argument('environment')),
            new RemoveIgnoredFiles,
            new RemovePintBinary,
            new RemoveVendorPlatformCheck,
            new ProcessAssets($this->option('asset-url')),
            new ExtractAssetsToSeparateDirectory($this->argument('environment')),
            new InjectHandlers($this->argument('environment')),
            new CollectSecrets($this->argument('environment')),
            new InjectErrorPages,
            new InjectRdsCertificate,
            new ExtractVendorToSeparateDirectory($this->argument('environment')),
            new CompressApplication($this->argument('environment')),
            new CompressVendor($this->argument('environment')),
            new PackageApplication($this->argument('environment')),
            new BuildContainerImage(
                $this->argument('environment'),
                $this->option('build-arg'),
                $this->option('build-option'),
                Manifest::dockerBuildArgs($this->argument('environment'))
            ),
        ])->each->__invoke();

        $time = (new DateTime)->diff($startedAt)->format('%im%Ss');

        Helpers::line();
        Helpers::line('<info>Project built successfully.</info> (' . $time . ')');
    }
}

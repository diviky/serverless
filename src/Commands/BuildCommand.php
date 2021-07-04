<?php

namespace Diviky\Serverless\Commands;

use DateTime;
use Diviky\Serverless\BuildProcess\CollectSecrets;
use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\BuildProcess\BuildContainerImage;
use Laravel\VaporCli\BuildProcess\CompressApplication;
use Laravel\VaporCli\BuildProcess\CompressVendor;
use Laravel\VaporCli\BuildProcess\ConfigureArtisan;
use Laravel\VaporCli\BuildProcess\ConfigureComposerAutoloader;
use Laravel\VaporCli\BuildProcess\CopyApplicationToBuildPath;
use Laravel\VaporCli\BuildProcess\ExecuteBuildCommands;
use Laravel\VaporCli\BuildProcess\ExtractAssetsToSeparateDirectory;
use Laravel\VaporCli\BuildProcess\ExtractVendorToSeparateDirectory;
use Laravel\VaporCli\BuildProcess\HarmonizeConfigurationFiles;
use Laravel\VaporCli\BuildProcess\InjectErrorPages;
use Laravel\VaporCli\BuildProcess\InjectHandlers;
use Laravel\VaporCli\BuildProcess\InjectRdsCertificate;
use Laravel\VaporCli\BuildProcess\ProcessAssets;
use Laravel\VaporCli\BuildProcess\RemoveIgnoredFiles;
use Laravel\VaporCli\BuildProcess\RemoveVendorPlatformCheck;
use Laravel\VaporCli\BuildProcess\SetBuildEnvironment;
use Laravel\VaporCli\BuildProcess\ValidateManifest;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;

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

        $startedAt = new DateTime();

        collect([
            new ValidateManifest($this->argument('environment')),
            new CopyApplicationToBuildPath(),
            new CollectSecrets($this->argument('environment')),
            new HarmonizeConfigurationFiles(),
            new SetBuildEnvironment($this->argument('environment'), $this->option('asset-url')),
            new ExecuteBuildCommands($this->argument('environment')),
            new ConfigureArtisan($this->argument('environment')),
            new ConfigureComposerAutoloader($this->argument('environment')),
            new RemoveIgnoredFiles(),
            new RemoveVendorPlatformCheck(),
            new ProcessAssets($this->option('asset-url')),
            new ExtractAssetsToSeparateDirectory(),
            new InjectHandlers($this->argument('environment')),
            new InjectErrorPages(),
            new InjectRdsCertificate(),
            new ExtractVendorToSeparateDirectory($this->argument('environment')),
            new CompressApplication($this->argument('environment')),
            new CompressVendor($this->argument('environment')),
            //new BuildContainerImage($this->argument('environment')),
        ])->each->__invoke();

        $time = (new DateTime())->diff($startedAt)->format('%im%Ss');

        Helpers::line();
        Helpers::line('<info>Project built successfully.</info> (' . $time . ')');
    }
}

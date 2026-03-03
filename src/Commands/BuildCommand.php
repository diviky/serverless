<?php

namespace Diviky\Serverless\Commands;

use DateTime;
use Diviky\Serverless\BuildProcess\BuildContainerImage;
use Diviky\Serverless\BuildProcess\CollectAndEncryptEnv;
use Diviky\Serverless\BuildProcess\CollectSecrets;
use Diviky\Serverless\BuildProcess\CompressApplication;
use Diviky\Serverless\BuildProcess\ConfigureArtisan;
use Diviky\Serverless\BuildProcess\ConfigureIndex;
use Diviky\Serverless\BuildProcess\CopyApplicationToBuildPath;
use Diviky\Serverless\BuildProcess\ExecuteBuildCommands;
use Diviky\Serverless\BuildProcess\ExtractAssetsToSeparateDirectory;
use Diviky\Serverless\BuildProcess\ModifyFiles;
use Diviky\Serverless\BuildProcess\PackageApplication;
use Diviky\Serverless\BuildProcess\RemoveIgnoredFiles;
use Diviky\Serverless\Concerns\ExecuteTrait;
use Laravel\VaporCli\BuildProcess\CompressVendor;
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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class BuildCommand extends VaporBuildCommand
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
            ->setName('build')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'production')
            ->addOption('asset-url', null, InputOption::VALUE_OPTIONAL, 'The asset base URL')
            ->addOption('build-arg', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build argument')
            ->addOption('build-option', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Docker build option')
            ->addOption('docker-build', null, InputOption::VALUE_NEGATABLE, 'Docker build command', true)
            ->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'AWS profile', null)
            ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'AWS region', null)
            ->setDescription('Build the project archive');
    }

    /**
     * Execute the command.
     */
    public function handle()
    {
        Helpers::ensure_api_token_is_available();

        Helpers::line('Building project...');

        if (Manifest::usesContainerImage($this->argument('environment') && $this->option('docker-build')) &&
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
            new CollectAndEncryptEnv($this->argument('environment')),
            new InjectErrorPages,
            new InjectRdsCertificate,
            new ModifyFiles($this->argument('environment')),
            new ExtractVendorToSeparateDirectory($this->argument('environment')),
            new CompressApplication($this->argument('environment')),
            new CompressVendor($this->argument('environment')),
            new PackageApplication($this->argument('environment')),
            $this->option('docker-build') ? new BuildContainerImage(
                $this->argument('environment'),
                $this->option('build-arg'),
                $this->option('build-option'),
                Manifest::dockerBuildArgs($this->argument('environment'))
            ) : null,
        ])->filter()->each->__invoke();

        $time = (new DateTime)->diff($startedAt)->format('%im%Ss');

        Helpers::line();
        Helpers::line('<info>Project built successfully.</info> (' . $time . ')');
    }
}

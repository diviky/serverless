<?php

namespace Diviky\Serverless\Commands;

use Diviky\Serverless\Concerns\ExecuteTrait;
use Diviky\Serverless\Manifest;
use Diviky\Serverless\Package;
use Laravel\VaporCli\Commands\BuildCommand as VaporBuildCommand;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Path;
use Symfony\Component\Console\Input\InputArgument;

class PackageCommand extends VaporBuildCommand
{
    use ExecuteTrait;

    /**
     * Configure the command options.
     */
    protected function configure()
    {
        $this
            ->setName('package')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment name', 'staging')
            ->setDescription('Package the application based on manifest configuration');
    }

    /**
     * Execute the command.
     */
    public function handle()
    {
        $environment = $this->argument('environment');
        $appPath = Path::app();

        if (!Manifest::shouldPackageApplication($environment)) {
            Helpers::info("Packaging is not enabled for the {$environment} environment.\n");

            return;
        }

        $config = Manifest::environment($environment)['package'] ?? [];
        $package = new Package($appPath);

        // Handle obfuscation based on manifest configuration
        if (isset($config['obfuscate']) && isset($config['obfuscate']['enabled']) && $config['obfuscate']['enabled'] === true) {
            Helpers::info("Starting obfuscation process...\n");
            $package->obfuscate($config['obfuscate']);
            Helpers::info("Obfuscation completed.\n");
        }

        // Handle PHAR creation based on manifest configuration
        if (isset($config['phar']) && $config['phar'] === true) {
            Helpers::info("Creating PHAR file...\n");
            $package->createPharFile();
            Helpers::info("PHAR file created.\n");
        }

        if (empty($config)) {
            Helpers::warn("No packaging configuration found in manifest for {$environment} environment.\n");
            Helpers::warn("Configure 'package' section in your vapor.yml or manifest file.\n");
        }
    }
}

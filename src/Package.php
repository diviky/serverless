<?php

namespace Diviky\Serverless;

use Illuminate\Filesystem\Filesystem;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Path;
use Symfony\Component\Process\Process;

class Package
{
    protected $appPath;

    protected $files;

    public function __construct($appPath = null)
    {
        $this->appPath = $appPath ?? Path::app();
        $this->files = new Filesystem;
    }

    public function obfuscate(array $options = [])
    {
        $directories = $options['directories'] ?? [];
        $arguments = $options['arguments'] ?? [];

        $defaultArguments = [
            '--no-shuffle-statements',
            '--no-obfuscate-function-name',
            '--no-obfuscate-class-name',
            '--no-obfuscate-namespace-name',
            '--no-obfuscate-method-name',
            '--no-obfuscate-trait-name',
            '--no-obfuscate-interface-name',
            '--no-obfuscate-constant-name',
            '--no-strip-indentation',
        ];

        $arguments = array_merge($defaultArguments, $arguments);

        foreach ($directories as $item) {
            $this->processItem($item, $arguments);
        }
    }

    protected function processItem($itemPath, $arguments)
    {
        $fullPath = $this->appPath . '/' . $itemPath;

        if (!file_exists($fullPath)) {
            Helpers::write("Skipping {$itemPath}: not found\n");

            return;
        }

        Helpers::write("Processing: {$itemPath}\n");

        $outputPath = $fullPath . '_backup_' . time();

        try {
            $this->obfuscateItem($fullPath, $outputPath, $arguments);

            // // Replace original with obfuscated version
            // if (file_exists($outputPath)) {
            //     if (file_exists($fullPath)) {
            //         if (is_dir($fullPath)) {
            //             $this->removeDirectory($fullPath);
            //         } else {
            //             unlink($fullPath);
            //         }
            //     }
            //     rename($outputPath, $fullPath);
            // }

            Helpers::write("Obfuscation completed for {$itemPath}\n");

        } catch (\Exception $e) {
            $this->handleError($e, $itemPath, $fullPath, $outputPath);

            return;
        }
    }

    protected function obfuscateItem($inputPath, $outputPath, $arguments)
    {
        $command = sprintf(
            'secure obfuscate %s -o %s %s',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath),
            implode(' ', $arguments)
        );

        $process = Process::fromShellCommandline($command, $this->appPath);
        $process->setTimeout(null);

        $process->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    protected function handleError(\Exception $e, $itemPath, $fullPath, $outputPath)
    {
        Helpers::abort("Error processing {$itemPath}: " . $e->getMessage() . "\n");

        // Restore from backup if something went wrong
        if ($outputPath && file_exists($outputPath)) {
            // Remove the failed attempt
            if (file_exists($fullPath)) {
                if (is_dir($fullPath)) {
                    $this->removeDirectory($fullPath);
                } else {
                    unlink($fullPath);
                }
            }

            Helpers::abort("Restored from backup due to error\n");
        }
    }

    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        return rmdir($dir);
    }

    public function createPharFile()
    {
        $this->fixRelativePath();
        $this->fixFiles();
        $this->fixPathname();

        $this->writeBoxConfigFile();

        $command = 'box compile';

        $process = Process::fromShellCommandline($command, $this->appPath);
        $process->setTimeout(null);

        $process->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    protected function fixRelativePath()
    {
        $command = 'LC_CTYPE=C find . -type f -name "*.php" -exec sed -i "" "s/realpath(/getRealPath(/g" {} +';

        $process = Process::fromShellCommandline($command, $this->appPath);
        $process->setTimeout(null);

        $process->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    protected function fixPathname()
    {
        $command = 'LC_CTYPE=C find . -type f -name "*.php" -exec sed -i "" "s/getRealPath(/getPathname(/g" {} +';

        $process = Process::fromShellCommandline($command, $this->appPath);
        $process->setTimeout(null);

        $process->mustRun(function ($type, $line) {
            Helpers::write($line);
        });
    }

    protected function fixFiles()
    {
        $this->fixPath();
    }

    protected function fixPath()
    {
        $file = $this->appPath . '/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/LoadConfiguration.php';

        $content = file_get_contents($file);

        $content = str_replace(
            [
                "\$config[basename(\$file->getRealPath(), '.php')] = require \$file->getRealPath();",
            ],
            [
                '$path = $file->getRealPath() ?: $file->getPathname();' . PHP_EOL . "\$config[basename(\$path, '.php')] = require \$path;",
            ],
            $content
        );

        file_put_contents($file, $content);
    }

    protected function writeBoxConfigFile()
    {
        if ($this->files->exists($this->appPath . '/box.json')) {
            return;
        }

        $config = [
            'main' => 'artisan',
            'output' => 'build/app.phar',
            'alias' => 'app.phar',
            'stub' => true,
            'compactors' => [
                'KevinGH\\Box\\Compactor\\Php',
            ],
            'dump-autoload' => true,
            'exclude-composer-files' => false,
            'banner' => false,
            'shebang' => false,
            'compression' => 'GZ',
            'algorithm' => 'SHA512',
            'directories' => [
                '.',
            ],
        ];

        $this->files->put($this->appPath . '/box.json', json_encode($config, JSON_PRETTY_PRINT));
    }
}

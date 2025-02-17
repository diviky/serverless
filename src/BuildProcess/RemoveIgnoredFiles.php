<?php

declare(strict_types=1);

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\BuildProcess\RemoveIgnoredFiles as BaseRemoveIgnoredFiles;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Finder\Finder;

class RemoveIgnoredFiles extends BaseRemoveIgnoredFiles
{
    /**
     * Execute the build process step.
     */
    public function __invoke(): void
    {
        Helpers::step('<options=bold>Removing Ignored Files</>');

        $this->removeDefaultIgnoredFiles();
        $this->removeDefaultIgnoredDirectories();
        $this->removeSymfonyTests();

        $this->removeUserIgnoredFiles();
    }

    /**
     * Remove the user ignored files specified in the project manifest.
     */
    protected function removeUserIgnoredFiles(): void
    {
        foreach (Manifest::ignoredFiles() as $pattern) {
            [$directory, $filePattern] = $this->parsePattern($pattern);

            if ($this->files->exists($directory . '/' . $filePattern) && $this->files->isDirectory($directory . '/' . $filePattern)) {
                Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $filePattern . '/');

                $this->files->deleteDirectory($directory . '/' . $filePattern, $preserve = false);
            } elseif ($this->files->isDirectory($directory)) {
                $files = (new Finder)
                    ->in($directory)
                    ->ignoreDotFiles(false)
                    ->name($filePattern);

                foreach ($files as $file) {
                    Helpers::step('<comment>Removing Ignored File:</comment> ' . str_replace(Path::app() . '/', '', $file->getRealPath()));

                    $this->files->delete($file->getRealPath());
                }
            }
        }
    }
}

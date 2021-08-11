<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Finder\Finder;
use Laravel\VaporCli\BuildProcess\RemoveIgnoredFiles as BaseRemoveIgnoredFiles;

class RemoveIgnoredFiles extends BaseRemoveIgnoredFiles
{
    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Removing Ignored Files</>');

        $this->removeDefaultIgnoredFiles();
        $this->removeDefaultIgnoredDirectories();
        $this->removeSymfonyTests();

        $this->removeUserIgnoredFiles();
    }

    /**
     * Remove the user ignored files specified in the project manifest.
     *
     * @return void
     */
    protected function removeUserIgnoredFiles()
    {
        foreach (Manifest::ignoredFiles() as $pattern) {
            [$directory, $filePattern] = $this->parsePattern($pattern);

            if ($this->files->exists($directory.'/'.$filePattern) && $this->files->isDirectory($directory.'/'.$filePattern)) {
                Helpers::step('<comment>Removing Ignored Directory:</comment> '.$filePattern.'/');

                $this->files->deleteDirectory($directory.'/'.$filePattern, $preserve = false);
            } else if ($this->files->isDirectory($directory)) {
                $files = (new Finder())
                            ->in($directory)
                            ->ignoreDotFiles(false)
                            ->name($filePattern);

                foreach ($files as $file) {
                    Helpers::step('<comment>Removing Ignored File:</comment> '.str_replace(Path::app().'/', '', $file->getRealPath()));

                    $this->files->delete($file->getRealPath());
                }
            }
        }
    }
}

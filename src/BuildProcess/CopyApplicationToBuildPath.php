<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\BuildProcess\CopyApplicationToBuildPath as BaseCopyApplicationToBuildPath;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Finder\SplFileInfo;

class CopyApplicationToBuildPath extends BaseCopyApplicationToBuildPath
{
    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Copying Application Files</>');

        $this->ensureBuildDirectoryExists();

        $this->copyApplication();
        $this->flushCacheFiles();
        $this->flushStorageDirectories();
        $this->removePossibleDockerignoreFile();
    }

    protected function copyApplication()
    {
        $this->copyApplicationFiles($this->getApplicationFiles());
    }

    protected function copyApplicationFiles($files)
    {
        foreach ($files as $file) {
            if ($file->isLink()) {
                $realPath = $file->getRealPath();
                if ($realPath) {
                    if (is_dir($realPath)) {
                        // For directories, we need to copy all contents recursively
                        $this->copyDirectoryContentsNative($realPath, $file->getRelativePathname());
                    } else {
                        // For files, create a new SplFileInfo with the real path
                        $file = new SplFileInfo($realPath, $file->getRelativePath(), $file->getRelativePathname());
                        $this->createFileForCopy($file);
                    }

                    continue;
                } else {
                    continue;
                }
            }

            $file->isDir()
                ? $this->createDirectoryForCopy($file)
                : $this->createFileForCopy($file);
        }
    }

    /**
     * Copy directory contents recursively.
     *
     * @param  string  $sourcePath
     * @param  string  $relativePath
     * @return void
     */
    protected function copyDirectoryContents($sourcePath, $relativePath)
    {
        $files = $this->files->allFiles($sourcePath);
        $this->copyApplicationFiles($files);
    }

    protected function copyDirectoryContentsNative($sourcePath, $relativePath)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $this->appPath . '/' . $relativePath . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                if (!is_dir(dirname($targetPath))) {
                    mkdir(dirname($targetPath), 0755, true);
                }
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}

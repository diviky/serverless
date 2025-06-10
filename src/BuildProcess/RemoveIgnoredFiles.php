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
     * Remove the user ignored files specified in the project manifest.
     */
    protected function removeUserIgnoredFiles(): void
    {
        $appPath = Path::app();
        Helpers::step('<comment>Build directory:</comment> ' . $appPath);

        // Show what actually exists in the build directory
        if ($this->files->isDirectory($appPath)) {
            $items = $this->files->directories($appPath);
            Helpers::step('<comment>Directories in build:</comment> ' . implode(', ', array_map('basename', $items)));

            $files = $this->files->files($appPath);
            if (count($files) > 0) {
                Helpers::step('<comment>Files in build root:</comment> ' . implode(', ', array_map('basename', array_slice($files, 0, 10))));
            }
        }

        foreach (Manifest::ignoredFiles() as $pattern) {
            $this->removePattern($pattern);
        }
    }

    /**
     * Remove files/directories matching the given pattern.
     */
    protected function removePattern(string $pattern): void
    {
        $appPath = Path::app();

        // Handle direct paths (like .git, .github)
        if (strpos($pattern, '*') === false) {
            $fullPath = $appPath . '/' . $pattern;

            Helpers::step('<comment>Checking pattern:</comment> ' . $pattern);
            Helpers::step('<comment>Full path:</comment> ' . $fullPath);

            if ($this->files->exists($fullPath)) {
                if ($this->files->isDirectory($fullPath)) {
                    Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $pattern . '/');
                    $this->files->deleteDirectory($fullPath, false);
                } else {
                    Helpers::step('<comment>Removing Ignored File:</comment> ' . $pattern);
                    $this->files->delete($fullPath);
                }
            } else {
                Helpers::step('<comment>Path does not exist:</comment> ' . $fullPath);

                // Check if it's a relative path issue - try without leading slash
                $alternativePath = $appPath . '/' . ltrim($pattern, '/');
                if ($alternativePath !== $fullPath && $this->files->exists($alternativePath)) {
                    if ($this->files->isDirectory($alternativePath)) {
                        Helpers::step('<comment>Removing Ignored Directory (alternative path):</comment> ' . $pattern . '/');
                        $this->files->deleteDirectory($alternativePath, false);
                    } else {
                        Helpers::step('<comment>Removing Ignored File (alternative path):</comment> ' . $pattern);
                        $this->files->delete($alternativePath);
                    }
                }
            }

            return;
        }

        // Handle patterns with wildcards
        if (strpos($pattern, '**/') !== false) {
            // Handle recursive patterns like vendor/**/vendor
            $this->removeRecursivePattern($pattern);
        } else {
            // Handle simple wildcard patterns
            [$directory, $filePattern] = $this->parsePattern($pattern);
            $this->removeFromDirectory($directory, $filePattern);
        }
    }

    /**
     * Remove files/directories matching recursive patterns.
     */
    protected function removeRecursivePattern(string $pattern): void
    {
        $appPath = Path::app();

        // For patterns like vendor/**/vendor, we want to find all 'vendor' directories
        // inside the main 'vendor' directory at any depth
        if (preg_match('/^(.+?)\/\*\*\/(.+)$/', $pattern, $matches)) {
            $basePath = $matches[1];
            $targetName = $matches[2];

            $searchPath = $appPath . '/' . $basePath;

            if ($this->files->isDirectory($searchPath)) {
                try {
                    $finder = new Finder;
                    $finder->in($searchPath)
                        ->directories()
                        ->ignoreDotFiles(false)
                        ->name($targetName);

                    // Collect all paths first to avoid iterator issues when deleting
                    $pathsToDelete = [];
                    foreach ($finder as $directory) {
                        $pathsToDelete[] = $directory->getRealPath();
                    }

                    // Now delete them, checking existence first
                    foreach ($pathsToDelete as $path) {
                        if ($this->files->exists($path) && $this->files->isDirectory($path)) {
                            $relativePath = str_replace($appPath . '/', '', $path);
                            Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $relativePath . '/');
                            $this->files->deleteDirectory($path, false);
                        }
                    }
                } catch (\Exception $e) {
                    // Skip if directory structure changes during iteration
                    Helpers::step('<comment>Skipping pattern due to directory changes:</comment> ' . $pattern);
                }
            }
        }
    }

    /**
     * Remove files/directories from a specific directory matching a pattern.
     */
    protected function removeFromDirectory(string $directory, string $filePattern): void
    {
        $appPath = Path::app();

        // Fix path construction - don't double the app path
        if (strpos($directory, $appPath) === 0) {
            // Directory already contains full path
            $fullDirectory = $directory;
        } else {
            // Directory is relative, prepend app path
            $fullDirectory = $appPath . '/' . $directory;
        }

        $fullPath = $fullDirectory . '/' . $filePattern;
        if ($this->files->exists($fullPath) && $this->files->isDirectory($fullPath)) {
            Helpers::step('<comment>Removing Ignored Directory:</comment> ' . str_replace($appPath . '/', '', $fullPath) . '/');
            $this->files->deleteDirectory($fullPath, false);

            return;
        }

        // Search for files/directories matching the pattern
        if ($this->files->isDirectory($fullDirectory)) {
            try {
                $finder = new Finder;
                $finder->in($fullDirectory)
                    ->ignoreDotFiles(false)  // This should allow .git and other dot files
                    ->ignoreVCS(false)       // This should ignore VCS exclusions
                    ->name($filePattern);

                // Collect all paths first to avoid iterator issues when deleting
                $pathsToDelete = [];
                foreach ($finder as $file) {
                    $pathsToDelete[] = [
                        'path' => $file->getRealPath(),
                        'isDir' => $file->isDir(),
                    ];
                }

                Helpers::step('<comment>Found ' . count($pathsToDelete) . ' items matching pattern:</comment> ' . $filePattern);

                // Now delete them, checking existence first
                foreach ($pathsToDelete as $item) {
                    if ($this->files->exists($item['path'])) {
                        $relativePath = str_replace($appPath . '/', '', $item['path']);

                        if ($item['isDir'] && $this->files->isDirectory($item['path'])) {
                            Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $relativePath . '/');
                            $this->files->deleteDirectory($item['path'], false);
                        } elseif (!$item['isDir'] && $this->files->isFile($item['path'])) {
                            Helpers::step('<comment>Removing Ignored File:</comment> ' . $relativePath);
                            $this->files->delete($item['path']);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Skip if directory structure changes during iteration
                Helpers::step('<comment>Skipping pattern due to directory changes:</comment> ' . $directory . '/' . $filePattern);
                Helpers::step('<comment>Error:</comment> ' . $e->getMessage());
            }
        } else {
            Helpers::step('<comment>Directory does not exist:</comment> ' . $fullDirectory);
        }
    }
}

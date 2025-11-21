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
     * Negation patterns (patterns starting with !).
     *
     * @var array
     */
    protected $negationPatterns = [];

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

        // Separate ignore patterns from negation patterns
        $ignorePatterns = [];
        $this->negationPatterns = [];

        foreach (Manifest::ignoredFiles() as $pattern) {
            // Remove quotes if present
            $pattern = trim($pattern, '"\'');

            if (strpos($pattern, '!') === 0) {
                // This is a negation pattern - remove the ! prefix
                $this->negationPatterns[] = substr($pattern, 1);
            } else {
                // This is a regular ignore pattern
                $ignorePatterns[] = $pattern;
            }
        }

        // Process ignore patterns (negation patterns will be checked during removal)
        foreach ($ignorePatterns as $pattern) {
            $this->removePattern($pattern);
        }
    }

    /**
     * Check if a path matches any negation pattern.
     *
     * @param  string  $relativePath  Path relative to app directory
     */
    protected function matchesNegationPattern(string $relativePath): bool
    {
        foreach ($this->negationPatterns as $negationPattern) {
            if ($this->pathMatchesPattern($relativePath, $negationPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a pattern (supports wildcards and recursive patterns).
     */
    protected function pathMatchesPattern(string $path, string $pattern): bool
    {
        // Normalize paths
        $path = str_replace('\\', '/', trim($path, '/'));
        $pattern = str_replace('\\', '/', trim($pattern, '/"'));

        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Handle recursive patterns like vendor/**/docs
        if (strpos($pattern, '**') !== false) {
            // Convert ** to regex that matches any number of directories
            $patternRegex = preg_quote($pattern, '/');
            // Replace **/ with pattern that matches zero or more directories
            $patternRegex = str_replace('\*\*\/', '([^\/]+\/)*', $patternRegex);
            // Replace standalone ** with pattern that matches anything
            $patternRegex = str_replace('\*\*', '.*', $patternRegex);
            // Replace single * with pattern that matches non-slash characters
            $patternRegex = str_replace('\*', '[^\/]*', $patternRegex);
            $patternRegex = '/^' . str_replace('/', '\/', $patternRegex) . '$/';

            return (bool) preg_match($patternRegex, $path);
        }

        // Handle simple wildcard patterns
        if (strpos($pattern, '*') !== false) {
            $patternRegex = preg_quote($pattern, '/');
            $patternRegex = str_replace('\*', '[^\/]*', $patternRegex);
            $patternRegex = '/^' . str_replace('/', '\/', $patternRegex) . '$/';

            return (bool) preg_match($patternRegex, $path);
        }

        // Simple prefix match for directories (handles both exact and subdirectory matches)
        if ($path === $pattern || strpos($path, $pattern . '/') === 0) {
            return true;
        }

        return false;
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

            if ($this->files->exists($fullPath)) {
                $relativePath = $pattern;

                // Check if this path matches a negation pattern
                if ($this->matchesNegationPattern($relativePath)) {
                    Helpers::step('<comment>Skipping (negated):</comment> ' . $pattern);

                    return;
                }

                if ($this->files->isDirectory($fullPath)) {
                    Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $pattern . '/');
                    $this->files->deleteDirectory($fullPath, false);
                } else {
                    Helpers::step('<comment>Removing Ignored File:</comment> ' . $pattern);
                    $this->files->delete($fullPath);
                }
            } else {
                // Check if it's a relative path issue - try without leading slash
                $alternativePath = $appPath . '/' . ltrim($pattern, '/');
                if ($alternativePath !== $fullPath && $this->files->exists($alternativePath)) {
                    $relativePath = ltrim($pattern, '/');

                    // Check if this path matches a negation pattern
                    if ($this->matchesNegationPattern($relativePath)) {
                        Helpers::step('<comment>Skipping (negated):</comment> ' . $pattern);

                        return;
                    }

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

        Helpers::step('<comment>Processing recursive pattern:</comment> ' . $pattern);

        // For patterns like vendor/**/.git, we want to find all '.git' directories
        // inside the main 'vendor' directory at any depth
        if (preg_match('/^(.+?)\/\*\*\/(.+)$/', $pattern, $matches)) {
            $basePath = $matches[1];
            $targetName = $matches[2];

            $searchPath = $appPath . '/' . $basePath;

            Helpers::step('<comment>Searching in:</comment> ' . $searchPath . ' <comment>for:</comment> ' . $targetName);

            if ($this->files->isDirectory($searchPath)) {
                try {
                    $finder = new Finder;
                    $finder->in($searchPath)
                        ->directories()
                        ->ignoreDotFiles(false)    // Allow .git and other dot directories
                        ->ignoreVCS(false)         // Don't ignore VCS directories
                        ->name($targetName);

                    // Collect all paths first to avoid iterator issues when deleting
                    $pathsToDelete = [];
                    foreach ($finder as $directory) {
                        $pathsToDelete[] = $directory->getRealPath();
                    }

                    Helpers::step('<comment>Found ' . count($pathsToDelete) . ' directories matching:</comment> ' . $targetName);

                    // Sort paths by depth (deepest first) to avoid deletion conflicts
                    usort($pathsToDelete, function ($a, $b) {
                        return substr_count($b, '/') - substr_count($a, '/');
                    });

                    // Now delete them, checking existence first and negation patterns
                    foreach ($pathsToDelete as $path) {
                        if ($this->files->exists($path) && $this->files->isDirectory($path)) {
                            $relativePath = str_replace($appPath . '/', '', $path);

                            // Check if this path matches a negation pattern
                            if ($this->matchesNegationPattern($relativePath)) {
                                Helpers::step('<comment>Skipping (negated):</comment> ' . $relativePath . '/');

                                continue;
                            }

                            Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $relativePath . '/');
                            $this->files->deleteDirectory($path, false);
                        }
                    }
                } catch (\Exception $e) {
                    // Skip if directory structure changes during iteration
                    Helpers::step('<comment>Skipping pattern due to directory changes:</comment> ' . $pattern . ' (' . $e->getMessage() . ')');
                }
            } else {
                Helpers::step('<comment>Base directory does not exist:</comment> ' . $searchPath);
            }
        } else {
            Helpers::step('<comment>Pattern does not match expected recursive format:</comment> ' . $pattern);
        }
    }

    /**
     * Parse a simple pattern into directory and file pattern.
     */
    protected function parsePattern($pattern)
    {
        $lastSlashPos = strrpos($pattern, '/');

        if ($lastSlashPos === false) {
            // No directory separator, pattern is just a filename
            return ['', $pattern];
        }

        $directory = substr($pattern, 0, $lastSlashPos);
        $filePattern = substr($pattern, $lastSlashPos + 1);

        return [$directory, $filePattern];
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
            $relativePath = str_replace($appPath . '/', '', $fullPath);

            // Check if this path matches a negation pattern
            if ($this->matchesNegationPattern($relativePath)) {
                Helpers::step('<comment>Skipping (negated):</comment> ' . $relativePath . '/');

                return;
            }

            Helpers::step('<comment>Removing Ignored Directory:</comment> ' . $relativePath . '/');
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

                // Now delete them, checking existence first and negation patterns
                foreach ($pathsToDelete as $item) {
                    if ($this->files->exists($item['path'])) {
                        $relativePath = str_replace($appPath . '/', '', $item['path']);

                        // Check if this path matches a negation pattern
                        if ($this->matchesNegationPattern($relativePath)) {
                            Helpers::step('<comment>Skipping (negated):</comment> ' . $relativePath);

                            continue;
                        }

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
            }
        }
    }
}

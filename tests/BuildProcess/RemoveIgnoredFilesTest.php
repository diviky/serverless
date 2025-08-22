<?php

declare(strict_types=1);

use Diviky\Serverless\BuildProcess\RemoveIgnoredFiles;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    // Create a temporary directory for testing
    $this->tempDir = sys_get_temp_dir().'/serverless_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->filesystem = new Filesystem;
    $this->removeIgnoredFiles = new RemoveIgnoredFiles;

    // Set the filesystem property using reflection
    $reflection = new ReflectionClass($this->removeIgnoredFiles);
    $filesProperty = $reflection->getProperty('files');
    $filesProperty->setAccessible(true);
    $filesProperty->setValue($this->removeIgnoredFiles, $this->filesystem);
});

afterEach(function () {
    // Clean up temporary directory
    if ($this->filesystem->exists($this->tempDir)) {
        $this->filesystem->deleteDirectory($this->tempDir);
    }
});

function createTestStructure($tempDir, $filesystem)
{
    $structure = [
        '.git' => [],
        'vendor' => [
            '.git' => [],
            'package1' => [
                '.git' => [],
                'vendor' => [],
                'nested' => [
                    'deep' => [
                        '.git' => [],
                    ],
                ],
            ],
            'package2' => [
                '.git' => [],
                'subpackage' => [
                    'vendor' => [],
                ],
            ],
        ],
        'app' => [],
        'config' => [],
    ];

    createDirectoryStructure($tempDir, $structure, $filesystem);
}

function createDirectoryStructure(string $basePath, array $structure, $filesystem): void
{
    foreach ($structure as $name => $children) {
        $path = $basePath.'/'.$name;
        $filesystem->makeDirectory($path, 0755, true);

        if (is_array($children) && ! empty($children)) {
            createDirectoryStructure($path, $children, $filesystem);
        }
    }
}

test('can parse simple patterns', function () {
    $reflection = new ReflectionClass($this->removeIgnoredFiles);
    $parsePatternMethod = $reflection->getMethod('parsePattern');
    $parsePatternMethod->setAccessible(true);

    // Test simple filename
    [$directory, $filePattern] = $parsePatternMethod->invoke($this->removeIgnoredFiles, '.git');
    expect($directory)->toBe('');
    expect($filePattern)->toBe('.git');

    // Test directory/file pattern
    [$directory, $filePattern] = $parsePatternMethod->invoke($this->removeIgnoredFiles, 'vendor/.git');
    expect($directory)->toBe('vendor');
    expect($filePattern)->toBe('.git');

    // Test nested directory pattern
    [$directory, $filePattern] = $parsePatternMethod->invoke($this->removeIgnoredFiles, 'vendor/package/.git');
    expect($directory)->toBe('vendor/package');
    expect($filePattern)->toBe('.git');
});

test('can remove recursive git patterns', function () {
    createTestStructure($this->tempDir, $this->filesystem);

    // Verify all .git directories exist before removal
    expect($this->filesystem->exists($this->tempDir.'/vendor/.git'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/.git'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/.git'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/deep/.git'))->toBeTrue();

    // Test recursive removal logic
    $appPath = $this->tempDir;
    $pattern = 'vendor/**/.git';

    if (preg_match('/^(.+?)\/\*\*\/(.+)$/', $pattern, $matches)) {
        $basePath = $matches[1];
        $targetName = $matches[2];
        $searchPath = $appPath.'/'.$basePath;

        if ($this->filesystem->isDirectory($searchPath)) {
            $finder = new \Symfony\Component\Finder\Finder;
            $finder->in($searchPath)
                ->directories()
                ->ignoreDotFiles(false)
                ->ignoreVCS(false)
                ->name($targetName);

            $pathsToDelete = [];
            foreach ($finder as $directory) {
                $pathsToDelete[] = $directory->getRealPath();
            }

            // Sort paths by depth (deepest first)
            usort($pathsToDelete, function ($a, $b) {
                return substr_count($b, '/') - substr_count($a, '/');
            });

            foreach ($pathsToDelete as $path) {
                if ($this->filesystem->exists($path) && $this->filesystem->isDirectory($path)) {
                    $this->filesystem->deleteDirectory($path, false);
                }
            }
        }
    }

    // Verify all .git directories under vendor are removed
    expect($this->filesystem->exists($this->tempDir.'/vendor/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/deep/.git'))->toBeFalse();

    // Verify that vendor directories themselves still exist
    expect($this->filesystem->exists($this->tempDir.'/vendor'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/deep'))->toBeTrue();
});

test('handles patterns with multiple levels', function () {
    createTestStructure($this->tempDir, $this->filesystem);

    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/vendor'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/subpackage/vendor'))->toBeTrue();

    // Test recursive removal for vendor directories
    $appPath = $this->tempDir;
    $pattern = 'vendor/**/vendor';

    if (preg_match('/^(.+?)\/\*\*\/(.+)$/', $pattern, $matches)) {
        $basePath = $matches[1];
        $targetName = $matches[2];
        $searchPath = $appPath.'/'.$basePath;

        if ($this->filesystem->isDirectory($searchPath)) {
            $finder = new \Symfony\Component\Finder\Finder;
            $finder->in($searchPath)
                ->directories()
                ->ignoreDotFiles(false)
                ->ignoreVCS(false)
                ->name($targetName);

            $pathsToDelete = [];
            foreach ($finder as $directory) {
                $pathsToDelete[] = $directory->getRealPath();
            }

            foreach ($pathsToDelete as $path) {
                if ($this->filesystem->exists($path) && $this->filesystem->isDirectory($path)) {
                    $this->filesystem->deleteDirectory($path, false);
                }
            }
        }
    }

    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/vendor'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/subpackage/vendor'))->toBeFalse();

    // Main vendor directory should still exist
    expect($this->filesystem->exists($this->tempDir.'/vendor'))->toBeTrue();
});

test('recursive pattern finds deeply nested git directories', function () {
    createTestStructure($this->tempDir, $this->filesystem);

    // Add even deeper nesting
    $this->filesystem->makeDirectory($this->tempDir.'/vendor/package1/nested/very/deep/structure/.git', 0755, true);
    $this->filesystem->makeDirectory($this->tempDir.'/vendor/package2/deeply/nested/folders/.git', 0755, true);

    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/very/deep/structure/.git'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/deeply/nested/folders/.git'))->toBeTrue();

    // Test recursive removal
    $appPath = $this->tempDir;
    $pattern = 'vendor/**/.git';

    if (preg_match('/^(.+?)\/\*\*\/(.+)$/', $pattern, $matches)) {
        $basePath = $matches[1];
        $targetName = $matches[2];
        $searchPath = $appPath.'/'.$basePath;

        if ($this->filesystem->isDirectory($searchPath)) {
            $finder = new \Symfony\Component\Finder\Finder;
            $finder->in($searchPath)
                ->directories()
                ->ignoreDotFiles(false)
                ->ignoreVCS(false)
                ->name($targetName);

            $pathsToDelete = [];
            foreach ($finder as $directory) {
                $pathsToDelete[] = $directory->getRealPath();
            }

            // Sort by depth (deepest first) to avoid conflicts
            usort($pathsToDelete, function ($a, $b) {
                return substr_count($b, '/') - substr_count($a, '/');
            });

            foreach ($pathsToDelete as $path) {
                if ($this->filesystem->exists($path) && $this->filesystem->isDirectory($path)) {
                    $this->filesystem->deleteDirectory($path, false);
                }
            }
        }
    }

    // Verify all .git directories are removed, including deeply nested ones
    expect($this->filesystem->exists($this->tempDir.'/vendor/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/deep/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/very/deep/structure/.git'))->toBeFalse();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/deeply/nested/folders/.git'))->toBeFalse();

    // Verify parent directories still exist
    expect($this->filesystem->exists($this->tempDir.'/vendor'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package1/nested/very/deep/structure'))->toBeTrue();
    expect($this->filesystem->exists($this->tempDir.'/vendor/package2/deeply/nested/folders'))->toBeTrue();
});

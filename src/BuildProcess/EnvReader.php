<?php

namespace Diviky\Serverless\BuildProcess;

use Dotenv\Dotenv;
use Dotenv\Environment\Adapter\EnvConstAdapter as V3EnvConstAdapter;
use Dotenv\Environment\Adapter\ServerConstAdapter as V3ServerConstAdapter;
use Dotenv\Environment\DotenvFactory;
use Dotenv\Repository\Adapter\EnvConstAdapter as V4orV5EnvConstAdapter;
use Dotenv\Repository\Adapter\ServerConstAdapter as V4orV5ServerConstAdapter;
use Dotenv\Repository\RepositoryBuilder;

trait EnvReader
{
    public static function readEnv($names, $paths = null)
    {
        $paths = $paths ?: __DIR__;

        if (\class_exists(RepositoryBuilder::class)) {
            $adapters = [
                V4orV5EnvConstAdapter::class,
                V4orV5ServerConstAdapter::class,
            ];

            if (\method_exists(RepositoryBuilder::class, 'addReader')) { // V5
                $repository = RepositoryBuilder::createWithNoAdapters();

                foreach ($adapters as $adapter) {
                    $repository = $repository
                        ->addReader($adapter)
                        ->addWriter($adapter);
                }
            } else { // V4
                $adapters = \array_map(function ($adapterClass) {
                    return new $adapterClass();
                }, $adapters);

                $repository = RepositoryBuilder::create()
                    ->withReaders($adapters)
                    ->withWriters($adapters);
            }

            return Dotenv::create(
                $repository->immutable()->make(),
                $paths,
                $names
            )->safeLoad();
        }   // V3

        return Dotenv::create($paths, $names, new DotenvFactory([
            new V3EnvConstAdapter(), new V3ServerConstAdapter(),
        ]))->safeLoad();
    }

    public static function getProjectEnv($appPath, $environment, $file='.env')
    {
        $secrets = [];

        if (\file_exists($appPath . '/' . $file . '.' . $environment)) {
            $secrets = static::readEnv($file . '.' . $environment, $appPath);
        } elseif (\file_exists($appPath . '/' . $file)) {
            $secrets = static::readEnv($file, $appPath);
        }

        return $secrets;
    }
}

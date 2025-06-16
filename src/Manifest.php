<?php

namespace Diviky\Serverless;

class Manifest extends \Laravel\VaporCli\Manifest
{
    public static function shouldSeparateAssets($environment)
    {
        $config = static::environment($environment);

        if (isset($config['separate-assets'])) {
            return $config['separate-assets'] ? true : false;
        }

        return $config['assets'] ? true : false;
    }

    /**
     * Get the default environment of the project.
     *
     * @return array
     */
    public static function environment($environment)
    {
        return static::current()['environments'][$environment] ?? [];
    }

    public static function image($environment)
    {
        return static::environment($environment)['image'] ?? static::name();
    }

    public static function bucket($environment)
    {
        $region = static::current()['region'] ?? 'us-east-1';
        $id = static::current()['id'] ?? time();

        return static::environment($environment)['assets'] ?? "vapor-{$region}-assets-{$id}";
    }

    public static function artifactBucket($environment)
    {
        $region = static::current()['region'] ?? 'us-east-1';
        $id = static::current()['id'] ?? time();

        return static::environment($environment)['artifact-bucket'] ?? "vapor-{$region}-artifacts-{$id}";
    }
}

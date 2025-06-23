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
        $region = static::provider()['region'] ?? 'us-east-1';
        $id = static::current()['id'] ?? time();
        $assets = static::environment($environment)['assets'] ?? null;

        return isset($assets) && is_string($assets) ? $assets : "vapor-{$region}-assets-{$id}";
    }

    public static function deploymentBucket($environment)
    {
        $region = static::provider()['region'] ?? 'us-east-1';
        $id = static::current()['id'] ?? time();
        $deploymentBucket = static::environment($environment)['deployment-bucket'] ?? null;

        return isset($deploymentBucket) && is_string($deploymentBucket) ? $deploymentBucket : "vapor-{$region}-deployment-{$id}";
    }

    public static function artifactBucket($environment)
    {
        $region = static::provider()['region'] ?? 'us-east-1';
        $id = static::current()['id'] ?? time();
        $artifacts = static::environment($environment)['artifact-bucket'] ?? null;

        return isset($artifacts) && is_string($artifacts) ? $artifacts : "vapor-{$region}-artifacts-{$id}";
    }

    public static function provider()
    {
        return static::current()['provider'] ?? [];
    }
}

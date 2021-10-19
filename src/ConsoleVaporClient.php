<?php

namespace Diviky\Serverless;

use Laravel\VaporCli\Aws\AwsStorageProvider;
use Laravel\VaporCli\ConsoleVaporClient as VaporConsoleVaporClient;
use Laravel\VaporCli\Helpers;

class ConsoleVaporClient extends VaporConsoleVaporClient
{
    /**
     * Get all of the providers attached to the account.
     *
     * @return array
     */
    public function providers()
    {
        return [[
            'id'   => 'aws',
            'name' => 'aws',
        ]];
    }

    /**
     * Get the project with the given ID.
     *
     * @param string $projectId
     *
     * @return array
     */
    public function project($projectId)
    {
        return [
            'cloudfront_status' => '',
            'asset_domains'     => [
                's3' => 'https://s3.com/',
            ],
        ];
    }

    /**
     * Create a new project.
     *
     * @param string $name
     * @param int    $providerId
     * @param string $region
     * @param mixed  $usesVanityDomain
     *
     * @return array
     */
    public function createProject($name, $providerId, $region, $usesVanityDomain)
    {
        return [
            'project' => [
                'id'                 => $name,
                'name'               => $name,
                'region'             => $region,
                'project'            => $name,
                'uses_vanity_domain' => $usesVanityDomain,
            ],
        ];
    }

    /**
     * Get a pre-signed storage URL for the given project.
     *
     * @param int    $projectId
     * @param string $uuid
     * @param string $environment
     * @param string $file
     * @param string $commit
     * @param string $commitMessage
     * @param string $vendorHash
     * @param string $cliVersion
     * @param string $coreVersion
     *
     * @return array
     */
    public function createArtifact(
        $projectId,
        $uuid,
        $environment,
        $file = null,
        $commit = null,
        $commitMessage = null,
        $vendorHash = null,
        $cliVersion = null,
        $coreVersion = null
    ) {
        Serverless::generate($environment);

        exit;
        //Serverless::deploy();

        return [
            'id' => null,
        ];

        $artifact = [];

        Helpers::app(AwsStorageProvider::class)->store($artifact['url'], [], $file, true);

        return $artifact;
    }

    /**
     * Make an HTTP request and display any validation errors.
     *
     * @param string $method
     * @param string $uri
     *
     * @return array
     */
    protected function requestWithErrorHandling($method, $uri, array $json = [])
    {
        return [];
    }

    /**
     * Make a request to the API and return the resulting JSON array.
     *
     * @param string $method
     * @param string $uri
     * @param int    $tries
     *
     * @return array
     */
    protected function request($method, $uri, array $json = [], $tries = 0)
    {
        return [];
    }
}

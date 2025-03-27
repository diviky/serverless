<?php

namespace Diviky\Serverless;

use Laravel\VaporCli\ConsoleVaporClient as VaporConsoleVaporClient;

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
            'id' => 'aws',
            'name' => 'aws',
        ]];
    }

    /**
     * Get the project with the given ID.
     *
     * @param  string  $projectId
     * @return array
     */
    public function project($projectId)
    {
        return [
            'cloudfront_status' => '',
            'asset_domains' => [
                's3' => 'https://s3.com/',
            ],
        ];
    }

    /**
     * Create a new project.
     *
     * @param  string  $name
     * @param  int  $providerId
     * @param  string  $region
     * @param  mixed  $usesVanityDomain
     * @return array
     */
    public function createProject($name, $providerId, $region, $usesVanityDomain)
    {
        return [
            'project' => [
                'id' => $name,
                'name' => $name,
                'region' => $region,
                'project' => $name,
                'uses_vanity_domain' => $usesVanityDomain,
            ],
        ];
    }

    /**
     * Make an HTTP request and display any validation errors.
     *
     * @param  string  $method
     * @param  string  $uri
     * @return array
     */
    protected function requestWithErrorHandling($method, $uri, array $json = [])
    {
        return [];
    }

    /**
     * Make a request to the API and return the resulting JSON array.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  int  $tries
     * @return array
     */
    protected function request($method, $uri, array $json = [], $tries = 0)
    {
        return [];
    }
}

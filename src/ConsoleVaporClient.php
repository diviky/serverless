<?php

namespace Diviky\Serverless;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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

        return $this->request('get', '/api/teams/' . Helpers::config('team') . '/providers');
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

        return $this->request('get', '/api/projects/' . $projectId);
    }

    /**
     * Create a new project.
     *
     * @param string $name
     * @param int    $providerId
     * @param string $region
     *
     * @return array
     */
    public function createProject($name, $providerId, $region)
    {
        return [
            'project' => [
                'id'      => $name,
                'name'    => $name,
                'region'  => $region,
                'project' => $name,
            ],
        ];

        return $this->requestWithErrorHandling('post', '/api/teams/' . Helpers::config('team') . '/projects', \array_filter([
            'cloud_provider_id' => $providerId,
            'name'              => $name,
            'region'            => $region,
        ]));
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
        $file,
        $commit = null,
        $commitMessage = null,
        $vendorHash = null,
        $cliVersion = null,
        $coreVersion = null
    ) {
        Serverless::generate($environment);die;
        //Serverless::deploy();

        return [
            'id' => null,
        ];

        $artifact = $this->requestWithErrorHandling('post', '/api/projects/' . $projectId . '/artifacts/' . $environment, [
            'uuid'           => $uuid,
            'commit'         => $commit,
            'commit_message' => $commitMessage,
            'vendor_hash'    => $vendorHash,
            'cli_version'    => $cliVersion,
            'core_version'   => $coreVersion,
        ]);

        Helpers::app(AwsStorageProvider::class)->store($artifact['url'], [], $file, true);

        try {
            $this->requestWithErrorHandling('post', '/api/artifacts/' . $artifact['id'] . '/receipt');
        } catch (ClientException $e) {
            Helpers::abort('Unable to upload deployment artifact to cloud storage.');
        }

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

        try {
            return $this->request($method, $uri, $json);
        } catch (ClientException $e) {
            $response = $e->getResponse();

            if (\in_array($response->getStatusCode(), [400, 422])) {
                $this->displayValidationErrors($response);

                exit(1);
            }

            throw $e;
        }
    }

    /**
     * Get a HTTP client instance.
     *
     * @return Client
     */
    protected function client()
    {
        return new Client([
            'base_uri' => $_ENV['VAPOR_API_BASE'] ?? \getenv('VAPOR_API_BASE') ?: 'https://vapor.laravel.com',
            // 'base_uri' => $_ENV['VAPOR_API_BASE'] ?? 'https://laravel-vapor.ngrok.io',
        ]);
    }
}

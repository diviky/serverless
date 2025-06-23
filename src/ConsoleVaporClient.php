<?php

namespace Diviky\Serverless;

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

    /**
     * Get a pre-signed storage URL for the given project.
     *
     * @param  int  $projectId
     * @param  string  $uuid
     * @param  string  $environment
     * @param  string  $file
     * @param  string  $commit
     * @param  string  $commitMessage
     * @param  string  $vendorHash
     * @param  string  $cliVersion
     * @param  string  $coreVersion
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
        // Get AWS profile and region from serverless.yml or environment
        $repositoryName = Manifest::image($environment);
        $bucketName = Manifest::artifactBucket($environment);
        $assetsBucketName = Manifest::bucket($environment);

        $artifactKey = 'releases/' . $uuid . '/';

        // Generate ECR details using AWS profile
        $ecrDetails = $this->generateEcrDetails($repositoryName);

        // Generate S3 pre-signed URLs for artifact uploads
        if ($vendorHash) {
            $vendor_url = $this->generateS3PresignedUrls($bucketName, $artifactKey . 'vendor.zip');
        } else {
            $vendor_url = null;
        }

        if ($file) {
            $artifact_url = $this->generateS3PresignedUrls($bucketName, $artifactKey . 'app.zip');
        } else {
            $artifact_url = null;
        }

        $artifact = [
            'id' => $uuid,
            'vendor_url' => $vendor_url,
            'container_registry_token' => $ecrDetails['registry_token'],
            'container_repository' => $ecrDetails['repository_uri'],
            'container_image_tag' => $ecrDetails['image_tag'],
            'url' => $artifact_url,
            'uses_container_image' => is_null($file),
            'artifact_bucket' => $bucketName,
            'path' => $artifactKey,
            'assets_bucket' => $assetsBucketName,
            'environment' => $environment,
        ];

        if ($file) {
            try {
                Helpers::app(AwsStorageProvider::class)->store($artifact['url'], [], $file, true);
            } catch (ClientException $e) {
                Helpers::abort('Unable to upload deployment artifact to cloud storage.');
            }
        }

        return $artifact;
    }

    /**
     * Get AWS profile from serverless.yml or environment.
     *
     * @return string
     */
    protected function getAwsProfile()
    {
        $profile = 'default';

        try {
            $manifest = Manifest::current();
            if (isset($manifest['profile'])) {
                $profile = $manifest['profile'];
            }
        } catch (\Exception $e) {
            // Fallback if manifest reading fails
        }

        // Check if profile exists, fallback to default if not
        $envProfile = env('AWS_PROFILE', $profile);

        return $envProfile;
    }

    /**
     * Get AWS region from serverless.yml or environment.
     *
     * @return string
     */
    protected function getAwsRegion()
    {
        try {
            $manifest = Manifest::current();
            if (isset($manifest['aws']['region'])) {
                return $manifest['aws']['region'];
            }
            if (isset($manifest['region'])) {
                return $manifest['region'];
            }
        } catch (\Exception $e) {
            // Fallback if manifest reading fails
        }

        return env('AWS_REGION', 'us-east-1');
    }

    /**
     * Get AWS credentials from manifest or environment.
     *
     * @return array
     */
    protected function getAwsCredentials()
    {
        try {
            $manifest = Manifest::current();
            if (isset($manifest['aws'])) {
                return [
                    'key' => $manifest['aws']['access_key'] ?? env('AWS_ACCESS_KEY_ID'),
                    'secret' => $manifest['aws']['secret_key'] ?? env('AWS_SECRET_ACCESS_KEY'),
                    'region' => $manifest['aws']['region'] ?? $this->getAwsRegion(),
                ];
            }
        } catch (\Exception $e) {
            // Fallback if manifest reading fails
        }

        return [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => $this->getAwsRegion(),
        ];
    }

    /**
     * Generate ECR details using AWS CLI with the specified profile.
     *
     * @param  string  $profile
     * @param  string  $region
     * @param  string  $projectName
     * @param  string  $uuid
     * @return array
     */
    protected function generateEcrDetails($repositoryName, $tag = 'latest')
    {
        $credentials = $this->getAwsCredentials();
        $profile = $this->getAwsProfile();

        $tag = $tag ?? 'latest';
        $repositoryName = strtolower($repositoryName);

        // Configure AWS clients with credentials or profile
        $clientConfig = [
            'region' => $credentials['region'],
            'version' => 'latest',
        ];

        if (!empty($credentials['key']) && !empty($credentials['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $credentials['key'],
                'secret' => $credentials['secret'],
            ];
        } elseif ($profile !== 'default') {
            $clientConfig['profile'] = $profile;
        }

        // Create STS client to get account ID
        $stsClient = new \Aws\Sts\StsClient($clientConfig);

        // Get AWS Account ID
        $identity = $stsClient->getCallerIdentity();
        $accountId = $identity['Account'];

        if (empty($accountId)) {
            throw new \Exception('Unable to get AWS Account ID');
        }

        // Repository name based on project
        $repositoryUri = "{$accountId}.dkr.ecr.{$credentials['region']}.amazonaws.com/{$repositoryName}";

        // Create ECR client
        $ecrClient = new \Aws\Ecr\EcrClient($clientConfig);

        // Create ECR repository if it doesn't exist
        try {
            $ecrClient->createRepository([
                'repositoryName' => $repositoryName,
            ]);
        } catch (\Aws\Exception\AwsException $e) {
            // Repository might already exist, ignore error
        }

        // Get ECR authorization token
        $authResult = $ecrClient->getAuthorizationToken();
        $authData = $authResult['authorizationData'][0] ?? null;

        if (!$authData || empty($authData['authorizationToken'])) {
            throw new \Exception('Unable to get ECR authorization token');
        }

        return [
            'registry_token' => $authData['authorizationToken'],
            'repository_uri' => $repositoryUri,
            'image_tag' => $tag,
        ];
    }

    /**
     * Generate S3 pre-signed URLs for artifact uploads.
     *
     * @param  string  $profile
     * @param  string  $region
     * @param  string  $projectName
     * @param  string  $uuid
     * @param  string|null  $vendorHash
     * @return array
     */
    protected function generateS3PresignedUrls($bucketName, $artifactKey)
    {
        $credentials = $this->getAwsCredentials();
        $profile = $this->getAwsProfile();

        try {
            // Configure AWS clients with credentials or profile
            $clientConfig = [
                'region' => $credentials['region'],
                'version' => 'latest',
            ];

            if (!empty($credentials['key']) && !empty($credentials['secret'])) {
                $clientConfig['credentials'] = [
                    'key' => $credentials['key'],
                    'secret' => $credentials['secret'],
                ];
            } elseif ($profile !== 'default') {
                $clientConfig['profile'] = $profile;
            }

            // Create S3 client
            $s3Client = new \Aws\S3\S3Client($clientConfig);

            // Check if bucket exists, create if it doesn't
            try {
                $s3Client->headBucket(['Bucket' => $bucketName]);
            } catch (\Aws\Exception\AwsException $e) {
                // Bucket doesn't exist, create it
                try {
                    $s3Client->createBucket([
                        'Bucket' => $bucketName,
                        'CreateBucketConfiguration' => [
                            'LocationConstraint' => $credentials['region'] !== 'us-east-1' ? $credentials['region'] : null,
                        ],
                    ]);
                } catch (\Aws\Exception\AwsException $createException) {
                    // Handle bucket creation error
                }
            }

            // Generate pre-signed URL for artifact upload
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => $bucketName,
                'Key' => $artifactKey,
            ]);

            $artifactUrl = (string) $s3Client->createPresignedRequest($cmd, '+1 hour')->getUri();

            return $artifactUrl;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get authorized URLs to store the given artifact assets.
     *
     * @param  array  $artifact
     * @return array
     */
    public function authorizeArtifactAssets($artifact, array $files, bool $fresh = false)
    {
        return [
            'store' => $this->createSignedAssets($artifact, $files),
            'copy' => [],
        ];
    }

    protected function createSignedAssets(array $artifact, array $files)
    {
        $bucketName = $artifact['assets_bucket'];

        $response = [];
        foreach ($files as $file) {
            $response[] = [
                'url' => $this->generateS3PresignedUrls($bucketName, $file['path']),
                'path' => $file['path'],
                'headers' => [
                    'Cache-Control' => 'public, max-age=31536000',
                ],
            ];
        }

        return $response;
    }

    public function deploy($artifactId, array $manifest, $debugMode)
    {
        return [
            'database' => null,
            'has_ended' => true,
            'id' => $artifactId,
            'steps' => [],
            'status' => null,

        ];
    }

    public function deployment($deploymentId)
    {
        return [
            'database' => null,
            'has_ended' => true,
            'id' => $deploymentId,
            'steps' => [],
            'status' => null,
        ];
    }
}

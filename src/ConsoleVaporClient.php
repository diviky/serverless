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

        $tag = $tag ?? 'latest';
        $repositoryName = strtolower($repositoryName);

        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create STS client to get account ID
        $stsClient = new \Aws\Sts\StsClient($clientConfig);

        // Get AWS Account ID
        $identity = $stsClient->getCallerIdentity();
        $accountId = $identity['Account'];

        if (empty($accountId)) {
            Helpers::abort('Unable to get AWS Account ID.');
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
            Helpers::abort('Unable to create ECR repository. ' . $e->getMessage());
        }

        // Get ECR authorization token
        $authResult = $ecrClient->getAuthorizationToken();
        $authData = $authResult['authorizationData'][0] ?? null;

        if (!$authData || empty($authData['authorizationToken'])) {
            Helpers::abort('Unable to get ECR authorization token.');
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

        try {
            // Get AWS client configuration
            $clientConfig = $this->getAwsClientConfig();

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
                } catch (\Aws\Exception\AwsException $e) {
                    // Handle bucket creation error
                    Helpers::abort('Unable to create S3 bucket. ' . $e->getMessage());
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
            Helpers::abort('Unable to upload deployment artifact to cloud storage. ' . $e->getMessage());
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

    /**
     * Get AWS client configuration.
     *
     * @return array
     */
    protected function getAwsClientConfig()
    {
        $credentials = $this->getAwsCredentials();
        $profile = $this->getAwsProfile();

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

        return $clientConfig;
    }

    /**
     * Get the environment variables for the given environment.
     *
     * @param  int  $projectId
     * @param  string  $environment
     * @return string
     */
    public function environmentVariables($projectId, $environment)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        // Build parameter path based on project and environment
        $parameterPath = "/{$projectId}/{$environment}/variables";
        $basePath = "/{$projectId}/{$environment}/";

        try {
            $parameters = [];

            // First, try to get the specific parameter that was stored by updateEnvironmentVariables
            try {
                $result = $ssmClient->getParameter([
                    'Name' => $parameterPath,
                    'WithDecryption' => true,
                ]);

                // If we found the specific parameter, add it to parameters array
                if (isset($result['Parameter'])) {
                    $parameters[] = $result['Parameter'];
                }
            } catch (\Aws\Exception\AwsException $e) {
                // Parameter not found at specific path, continue
            }

            // Check if variables were stored in chunks
            $metadataPath = "/{$projectId}/{$environment}/variables_metadata";
            try {
                $metadataResult = $ssmClient->getParameter([
                    'Name' => $metadataPath,
                    'WithDecryption' => false,
                ]);

                if (isset($metadataResult['Parameter'])) {
                    $metadata = json_decode($metadataResult['Parameter']['Value'], true);

                    if ($metadata && isset($metadata['total_chunks'])) {
                        // Retrieve all chunks
                        for ($i = 0; $i < $metadata['total_chunks']; $i++) {
                            $chunkPath = "/{$projectId}/{$environment}/variables_chunk_{$i}";

                            try {
                                $chunkResult = $ssmClient->getParameter([
                                    'Name' => $chunkPath,
                                    'WithDecryption' => true,
                                ]);

                                if (isset($chunkResult['Parameter'])) {
                                    $parameters[] = $chunkResult['Parameter'];
                                }
                            } catch (\Aws\Exception\AwsException $e) {
                                // Continue if chunk not found
                            }
                        }
                    }
                }
            } catch (\Aws\Exception\AwsException $e) {
                // No metadata found, continue with regular retrieval
            }

            // Also get all parameters under the base path for backward compatibility
            try {
                $pathBasedParameters = $this->getParametersByPath($ssmClient, $basePath);

                // Merge path-based parameters with existing parameters (avoid duplicates)
                $existingNames = array_map(function ($p) {
                    return $p['Name'];
                }, $parameters);

                foreach ($pathBasedParameters as $pathParam) {
                    if (!in_array($pathParam['Name'], $existingNames)) {
                        $parameters[] = $pathParam;
                    }
                }
            } catch (\Exception $e) {
                // Continue if path-based retrieval fails
            }

            // Convert parameters to environment variable format and concatenate
            $envVars = [];
            foreach ($parameters as $parameter) {
                $name = $parameter['Name'];
                $value = $parameter['Value'];

                // Extract the parameter name from the full path
                $paramName = basename($name);

                // Skip metadata parameters
                if ($paramName === 'variables_metadata') {
                    continue;
                }

                // Parse the parameter value and extract key-value pairs
                $parsedVars = $this->parseParameterValue($paramName, $value);
                $envVars = array_merge($envVars, $parsedVars);
            }

            // Return concatenated string with newlines
            return implode("\n", $envVars);

        } catch (\Exception $e) {
            Helpers::abort('Unable to get environment variables from parameter store. ' . $e->getMessage());
        }
    }

    /**
     * Parse parameter value and extract key-value pairs.
     *
     * @param  string  $paramName
     * @param  string  $value
     * @return array
     */
    protected function parseParameterValue($paramName, $value)
    {
        $envVars = [];

        // Try to parse as JSON first
        if ($this->isJson($value)) {
            $decodedValues = json_decode($value, true);
            if (is_array($decodedValues)) {
                foreach ($decodedValues as $key => $val) {
                    // Resolve SSM references in JSON values
                    $resolvedValue = $this->resolveSsmReferences($val);
                    $envVars[] = "{$key}={$resolvedValue}";
                }

                return $envVars;
            }
        }

        // Try to parse as key=value pairs (multiline)
        if (strpos($value, '=') !== false) {
            $lines = explode("\n", $value);
            $hasKeyValuePairs = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '=') === false) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    // Resolve SSM references in values
                    $resolvedValue = $this->resolveSsmReferences($val);
                    $envVars[] = "{$key}={$resolvedValue}";
                    $hasKeyValuePairs = true;
                }
            }

            if ($hasKeyValuePairs) {
                return $envVars;
            }
        }

        // Try to parse as YAML-like format (key: value)
        if (strpos($value, ':') !== false) {
            $lines = explode("\n", $value);
            $hasYamlPairs = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, ':') === false) {
                    continue;
                }

                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    // Remove quotes if present
                    $val = trim($val, '"\'');
                    // Resolve SSM references in values
                    $resolvedValue = $this->resolveSsmReferences($val);
                    $envVars[] = "{$key}={$resolvedValue}";
                    $hasYamlPairs = true;
                }
            }

            if ($hasYamlPairs) {
                return $envVars;
            }
        }

        // Try to parse as comma-separated key=value pairs
        if (strpos($value, ',') !== false && strpos($value, '=') !== false) {
            $pairs = explode(',', $value);
            $hasCommaPairs = false;

            foreach ($pairs as $pair) {
                $pair = trim($pair);
                if (strpos($pair, '=') !== false) {
                    $parts = explode('=', $pair, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        // Resolve SSM references in values
                        $resolvedValue = $this->resolveSsmReferences($val);
                        $envVars[] = "{$key}={$resolvedValue}";
                        $hasCommaPairs = true;
                    }
                }
            }

            if ($hasCommaPairs) {
                return $envVars;
            }
        }

        // If none of the above formats match, treat as a single key=value
        $resolvedValue = $this->resolveSsmReferences($value);
        $envVars[] = "{$paramName}={$resolvedValue}";

        return $envVars;
    }

    /**
     * Resolve SSM parameter references in a value.
     *
     * @param  string  $value
     * @return string
     */
    protected function resolveSsmReferences($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Pattern to match ${ssm:/path/to/param} or ${ssm:/path/to/param:version}
        $pattern = '/\$\{ssm:([^}]+)\}/';

        return preg_replace_callback($pattern, function ($matches) {
            $ssmPath = $matches[1];

            try {
                // Get AWS client configuration
                $clientConfig = $this->getAwsClientConfig();

                // Create SSM client
                $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

                // Parse path and version if provided
                $pathParts = explode(':', $ssmPath);
                $parameterName = $pathParts[0];
                $version = isset($pathParts[1]) ? $pathParts[1] : null;

                // Prepare parameter request
                $params = [
                    'Name' => $parameterName,
                    'WithDecryption' => true,
                ];

                if ($version && is_numeric($version)) {
                    $params['ParameterValue'] = $version;
                }

                // Get the parameter value
                $result = $ssmClient->getParameter($params);
                $parameterValue = $result['Parameter']['Value'];

                // Handle StringList parameters (comma-separated values)
                if ($result['Parameter']['Type'] === 'StringList') {
                    // Return as comma-separated string or process as needed
                    return $parameterValue;
                }

                return $parameterValue;

            } catch (\Exception $e) {
                // Return the original reference if resolution fails
                // This prevents breaking the entire configuration due to one bad reference
                return $matches[0];
            }
        }, $value);
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param  string  $string
     * @return bool
     */
    protected function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get parameters from AWS Parameter Store by path.
     *
     * @param  \Aws\Ssm\SsmClient  $ssmClient
     * @param  string  $path
     * @return array
     */
    protected function getParametersByPath($ssmClient, $path)
    {
        $parameters = [];
        $nextToken = null;

        do {
            $params = [
                'Path' => $path,
                'Recursive' => true,
                'WithDecryption' => true,
            ];

            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $result = $ssmClient->getParametersByPath($params);
            $parameters = array_merge($parameters, $result['Parameters'] ?? []);
            $nextToken = $result['NextToken'] ?? null;

        } while ($nextToken);

        return $parameters;
    }

    /**
     * Update the environment variables for the given environment.
     *
     * @param  int  $projectId
     * @param  string  $environment
     * @param  string  $variables
     * @return array
     */
    public function updateEnvironmentVariables($projectId, $environment, $variables)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        // Build parameter path based on project and environment
        $parameterPath = "/{$projectId}/{$environment}/variables";

        try {
            // Check if variables exceed Standard tier limit (4096 characters)
            if (strlen($variables) <= 4096) {
                // Use Standard tier for small parameters
                $ssmClient->putParameter([
                    'Name' => $parameterPath,
                    'Value' => $variables,
                    'Type' => 'SecureString',
                    'Tier' => 'Standard',
                    'Overwrite' => true,
                    'Description' => "Environment variables for {$projectId}/{$environment}",
                ]);
            } else {
                // For larger parameters, use Advanced tier (supports up to 8KB)
                if (strlen($variables) <= 8192) {
                    $ssmClient->putParameter([
                        'Name' => $parameterPath,
                        'Value' => $variables,
                        'Type' => 'SecureString',
                        'Tier' => 'Advanced',
                        'Overwrite' => true,
                        'Description' => "Environment variables for {$projectId}/{$environment} (Advanced tier)",
                    ]);
                } else {
                    // For very large variable sets, split into multiple parameters
                    $this->storeVariablesInChunks($ssmClient, $projectId, $environment, $variables);
                }
            }
        } catch (\Exception $e) {
            Helpers::abort('Unable to update environment variables in parameter store. ' . $e->getMessage());
        }
    }

    /**
     * Store large variable sets by splitting into multiple parameters.
     *
     * @param  \Aws\Ssm\SsmClient  $ssmClient
     * @param  string  $projectId
     * @param  string  $environment
     * @param  string  $variables
     * @return void
     */
    protected function storeVariablesInChunks($ssmClient, $projectId, $environment, $variables)
    {
        // Parse variables into individual key-value pairs
        $envVars = [];
        $lines = explode("\n", $variables);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key=value format
            if (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);

                    if (!empty($key)) {
                        $envVars[$key] = $value;
                    }
                }
            }
        }

        // Group variables into chunks that fit within the 4KB limit
        $chunks = [];
        $currentChunk = [];
        $currentSize = 0;
        $maxChunkSize = 4000; // Leave some buffer for JSON formatting

        foreach ($envVars as $key => $value) {
            $pair = "{$key}={$value}";
            $pairSize = strlen($pair) + 1; // +1 for newline

            // If adding this pair would exceed the limit, start a new chunk
            if ($currentSize + $pairSize > $maxChunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = [];
                $currentSize = 0;
            }

            $currentChunk[$key] = $value;
            $currentSize += $pairSize;
        }

        // Add the last chunk
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        // Store each chunk as a separate parameter
        foreach ($chunks as $index => $chunk) {
            $chunkPath = "/{$projectId}/{$environment}/variables_chunk_{$index}";

            // Convert chunk to string format
            $chunkVariables = [];
            foreach ($chunk as $key => $value) {
                $chunkVariables[] = "{$key}={$value}";
            }
            $chunkContent = implode("\n", $chunkVariables);

            $ssmClient->putParameter([
                'Name' => $chunkPath,
                'Value' => $chunkContent,
                'Type' => 'SecureString',
                'Tier' => 'Standard',
                'Overwrite' => true,
                'Description' => "Environment variables chunk {$index} for {$projectId}/{$environment}",
            ]);
        }

        // Store metadata about the chunks
        $metadataPath = "/{$projectId}/{$environment}/variables_metadata";
        $metadata = json_encode([
            'total_chunks' => count($chunks),
            'chunk_pattern' => "/{$projectId}/{$environment}/variables_chunk_",
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $ssmClient->putParameter([
            'Name' => $metadataPath,
            'Value' => $metadata,
            'Type' => 'String',
            'Tier' => 'Standard',
            'Overwrite' => true,
            'Description' => "Metadata for chunked environment variables for {$projectId}/{$environment}",
        ]);
    }

    /**
     * Delete the given environment.
     *
     * @param  string  $projectId
     * @param  string  $environment
     * @return array
     */
    public function deleteEnvironment($projectId, $environment)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        try {
            // Build parameter paths to delete
            $parameterPaths = [
                "/{$projectId}/{$environment}/variables",
            ];

            foreach ($parameterPaths as $path) {
                try {
                    // Get all parameters under this path
                    if (substr($path, -1) === '/') {
                        // For paths ending with /, get all parameters recursively
                        $parameters = $this->getParametersByPath($ssmClient, $path);

                        foreach ($parameters as $parameter) {
                            try {
                                $ssmClient->deleteParameter([
                                    'Name' => $parameter['Name'],
                                ]);
                            } catch (\Aws\Exception\AwsException $e) {
                                Helpers::abort("Failed to delete {$parameter['Name']}: " . $e->getMessage());
                            }
                        }
                    } else {
                        // For specific parameter paths
                        try {
                            $ssmClient->deleteParameter([
                                'Name' => $path,
                            ]);
                        } catch (\Aws\Exception\AwsException $e) {
                            // Parameter might not exist, which is okay
                            if ($e->getAwsErrorCode() !== 'ParameterNotFound') {
                                Helpers::abort("Failed to delete {$path}: " . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Helpers::abort("Error processing path {$path}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Helpers::abort('Failed to delete environment. ' . $e->getMessage());
        }
    }

    /**
     * Get all of the secrets for the given environment.
     *
     * @param  string  $projectId
     * @param  string  $environment
     * @return array
     */
    public function secrets($projectId, $environment)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        // Build secrets path based on project and environment
        $secretsPath = "/{$projectId}/{$environment}/secrets/";

        try {
            // Get all secret parameters under the secrets path
            $parameters = $this->getParametersByPath($ssmClient, $secretsPath);

            $secrets = [];
            foreach ($parameters as $parameter) {
                $name = $parameter['Name'];
                $value = $parameter['Value'];

                // Extract the secret name from the full path
                $secretName = basename($name);

                $secrets[] = [
                    'id' => $name, // Use full path as ID for deletion
                    'name' => $secretName,
                    'value' => $value,
                    'type' => $parameter['Type'],
                    'created_at' => $parameter['LastModifiedDate'] ?? null,
                    'updated_at' => $parameter['LastModifiedDate'] ?? null,
                ];
            }

            return $secrets;

        } catch (\Exception $e) {
            Helpers::abort('Unable to get secrets from parameter store. ' . $e->getMessage());
        }
    }

    /**
     * Store a secret for the given environment.
     *
     * @param  string  $projectId
     * @param  string  $environment
     * @param  string  $name
     * @param  string  $value
     * @return array
     */
    public function storeSecret($projectId, $environment, $name, $value)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        // Build secret parameter path
        $secretPath = "/{$projectId}/{$environment}/secrets/{$name}";

        try {
            // Determine parameter tier based on value size
            $tier = 'Standard';
            $type = 'SecureString';

            if (strlen($value) > 4096) {
                if (strlen($value) <= 8192) {
                    $tier = 'Advanced';
                } else {
                    Helpers::abort('Secret value too large. Maximum size is 8KB for secrets.');
                }
            }

            // Store the secret
            $ssmClient->putParameter([
                'Name' => $secretPath,
                'Value' => $value,
                'Type' => $type,
                'Tier' => $tier,
                'Overwrite' => true,
                'Description' => "Secret '{$name}' for {$projectId}/{$environment}",
            ]);
        } catch (\Exception $e) {
            Helpers::abort('Unable to store secret in parameter store. ' . $e->getMessage());
        }
    }

    /**
     * Delete the given secret.
     *
     * @param  string  $secretId
     * @return void
     */
    public function deleteSecret($secretId)
    {
        // Get AWS client configuration
        $clientConfig = $this->getAwsClientConfig();

        // Create SSM client
        $ssmClient = new \Aws\Ssm\SsmClient($clientConfig);

        try {
            // Delete the secret parameter
            $ssmClient->deleteParameter([
                'Name' => $secretId, // secretId is the full parameter path
            ]);

        } catch (\Aws\Exception\AwsException $e) {
            if ($e->getAwsErrorCode() !== 'ParameterNotFound') {
                Helpers::abort('Unable to delete secret from parameter store. ' . $e->getMessage());
            }
            // If parameter not found, consider it already deleted (success)
        } catch (\Exception $e) {
            Helpers::abort('Unable to delete secret from parameter store. ' . $e->getMessage());
        }
    }
}

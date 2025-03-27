<?php

declare(strict_types=1);

namespace Diviky\Serverless;

use Diviky\Serverless\Concerns\EnvReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Cdk
{
    use EnvReader;

    /**
     * Write a fresh main manifest file for the given environment.
     *
     * @param  string  $stage
     */
    public static function generate($stage): void
    {
        $manifest = Manifest::current();

        $env = $manifest['environments'][$stage];
        $region = $manifest['region'] ?? 'ap-south-1';
        $name = $manifest['name'];
        $runtime = $env['runtime'];
        $image = $manifest['name'];
        $layers = null;
        $uuid = \date('ymdhm');
        $account_id = $manifest['id'];

        $queue_name = $stage.'_default';
        $queues = $env['queues'] ?? false;

        if ($queues !== false) {
            $queues = $queues === true ? [$queue_name] : $queues;
            $queues = ! is_array($queues) ? [$queues] : $queues;
        }

        $docker = $runtime == 'docker' || $runtime == 'docker-arm';

        $env['layers'] = $env['layers'] ?? [];

        if (! $docker) {
            $layers = self::toLayers($runtime, $region);
            $layers = \array_filter(\array_merge($layers, $env['layers']));
        }

        // arn:aws:lambda:ap-south-1:959512994844:layer:vapor-php-74:11

        $yaml = [
            'org' => $manifest['org'] ?? $name,
            'service' => $manifest['service'] ?? $name,
        ];

        $secrets = [];
        $resources = [];

        if (! empty($env['copy-env'])) {
            if ($docker) {
                $secrets = static::getProjectEnv(Path::app(), $stage, '.env.docker');
            }

            if (empty($secrets)) {
                $secrets = static::getProjectEnv(Path::app(), $stage, '.env');
            }
        }

        $environment = \array_merge([
            'VAPOR_SSM_PATH' => $env['ssm'] ?? '/'.$yaml['org'].'/'.$stage.'/'.$name,
            'VAPOR_SSM_VARIABLES' => '[]',
            'VAPOR_SERVERLESS_DB' => 'false',
            'VAPOR_MAINTENANCE_MODE' => 'false',
            'VAPOR_MAINTENANCE_MODE_SECRET' => 'secret',
            'VAPOR_ENVIRONMENT' => $stage,
            'VAPOR_PROJECT' => $name,
            'LOG_CHANNEL' => 'stderr',
            'LOG_STDERR_FORMATTER' => 'Laravel\Vapor\Logging\JsonFormatter',
            'APP_CONFIG_CACHE' => '/tmp/storage/bootstrap/cache/config.php',
            'LD_LIBRARY_PATH' => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
            'PATH' => '/opt/bin:/usr/local/bin:/usr/bin/:/bin',
            'XDG_CONFIG_HOME' => '/tmp',
            'APP_VANITY_URL' => '',
        ], $secrets);

        if (isset($env['octane'])) {
            $environment = \array_merge([
                'APP_RUNNING_IN_OCTANE' => 'true',
            ], $environment);

            if (isset($env['octane-database-session-persist'])) {
                $environment = \array_merge([
                    'OCTANE_DATABASE_SESSION_PERSIST' => 'true',
                ], $environment);
            }

            if (isset($env['octane-database-session-ttl']) && is_numeric($env['octane-database-session-ttl'])) {
                $environment = \array_merge([
                    'OCTANE_DATABASE_SESSION_TTL' => $env['octane-database-session-ttl'],
                ], $environment);
            }
        }

        $bucket = null;
        $bucket_prefix = null;
        if (isset($env['assets']) && $env['assets'] !== false) {
            $bucket = \is_string($env['assets']) ? $env['assets'] : 'com.${self:org}.${self:provider.region}.assets';
            $bucket_prefix = $stage.'/'.$uuid;

            if (isset($env['asset-domain']) && is_string($env['asset-domain'])) {
                $assets = Str::beforeLast($env['asset-domain'], '/').'/'.$bucket_prefix;
            } else {
                $assets = 'https://s3.${self:provider.region}.amazonaws.com/'.$bucket.'/'.$bucket_prefix;
            }

            $environment['ASSET_URL'] = $assets;
            $environment['MIX_URL'] = $assets;
        }

        $environment = \array_merge($environment, self::envVarsToArray($env['environment'] ?? null));

        $yaml['provider'] = \array_filter([
            'name' => 'aws',
            'region' => $region,
            'stage' => $stage,
            'profile' => $manifest['profile'] ?? null,
            'runtime' => 'provided.al2',
            'architecture' => $manifest['architecture'] ?? ($runtime == 'docker-arm' ? 'arm64' : null),
            'environment' => $environment,
            'apiGateway' => [
                'shouldStartNameWithService' => true,
            ],
            'deploymentBucket' => [
                'name' => $env['deployment-bucket'] ?? 'com.${self:org}.${self:provider.region}.deploys',
                'maxPreviousDeploymentArtifacts' => 10,
                'blockPublicAccess' => true,
            ],
            'iam' => [
                'role' => [
                    'statements' => [[
                        'Effect' => 'Allow',
                        'Action' => [
                            'route53:*',
                            's3:*',
                            'ses:*',
                            'sqs:*',
                            'dynamodb:*',
                            'apigateway:*',
                            'kms:Decrypt',
                            'cloudformation:*',
                            'secretsmanager:GetSecretValue',
                            'ssm:GetParameters',
                            'ssm:GetParameter',
                            'lambda:invokeFunction',
                            'acm:ListCertificates',
                            'cloudfront:UpdateDistribution',
                        ],
                        'Resource' => '*',
                    ]],
                ],
            ],
            'vpc' => \array_filter([
                'subnetIds' => $env['subnets'] ?? null,
                'securityGroupIds' => $env['security-groups'] ?? null,
            ]),
        ]);

        if ($docker) {
            if (empty($env['image'])) {
                $yaml['provider']['ecr'] = [
                    'images' => [
                        $image => [
                            'path' => Path::app(),
                            'file' => \file_exists($stage.'.Dockerfile') ? $stage.'.Dockerfile' : '.Dockerfile',
                            'platform' => $runtime == 'docker-arm' ? 'linux/arm64' : 'linux/amd64',
                            'buildOptions' => self::formatBuildOptions($stage),
                            'buildArgs' => self::formatBuildArguments($stage),
                        ],
                    ],
                ];
            } elseif (isset($env['image']) && is_string($env['image'])) {
                $yaml['provider']['ecr'] = [
                    'images' => [
                        $image => [
                            'uri' => $env['image'],
                        ],
                    ],
                ];

                $env['image'] = $image;
            }

        } else {
            $yaml['package'] = [
                'artifact' => 'app.zip',
            ];
        }

        $yaml['functions'] = [];
        $yaml['resources'] = [];
        $yaml['custom'] = [];

        $yaml['provider']['httpApi'] = [
            'cors' => true,
        ];

        $web = [
            'handler' => 'vaporHandler.handle',
            'url' => [
                'cors' => true,
            ],
            'timeout' => $env['timeout'] ?? 60,
            'memorySize' => $env['memory'] ?? 1024,
            'reservedConcurrency' => $env['concurrency'] ?? null,
            'provisionedConcurrency' => $env['capacity'] ?? null,
            'layers' => $layers,
            'events' => [
                [
                    'httpApi' => [
                        'method' => '*',
                        'path' => '/',
                    ],
                ],
                [
                    'schedule' => [
                        'enabled' => true,
                        'rate' => 'rate(5 minutes)',
                        'input' => [
                            'vaporWarmer' => true,
                            'concurrency' => $env['warm'] ?? 10,
                            'functionAlias' => $stage,
                            'functionName' => 'arn:aws:lambda:${self:provider.region}:${aws:accountId}:function:'.$name.'-'.$stage.'-web',
                        ],
                    ],
                ],
            ],
        ];

        if (isset($env['web'], $env['web']['events']) && is_array($env['web']['events'])) {
            $web['events'] = array_merge($web['events'], $env['web']['events']);
        }

        if (isset($env['web'], $env['web']['targets']) && is_array($env['web']['targets'])) {
            foreach ($env['web']['targets'] as $value) {
                $resources[Str::studly($value['name'].'-tg')] = self::createTargetGroup('web', $value);
            }
        }

        $queue = [
            'handler' => 'vaporHandler.handle',
            'environment' => [
                'APP_RUNNING_IN_CONSOLE' => 'true',
            ],
            'timeout' => $env['queue-timeout'] ?? ($env['timeout'] ?? 60),
            'memorySize' => $env['queue-memory'] ?? null,
            'reservedConcurrency' => $env['queue-concurrency'] ?? null,
            'layers' => $layers,
            'events' => [],
        ];

        if (isset($env['queue'], $env['queue']['events']) && is_array($env['queue']['events'])) {
            $queue['events'] = array_merge($queue['events'], $env['queue']['events']);
        }

        $schedule = [
            'handler' => 'vaporHandler.handle',
            'environment' => [
                'APP_RUNNING_IN_CONSOLE' => 'true',
            ],
            'timeout' => $env['cli-timeout'] ?? ($env['timeout'] ?? 60),
            'memorySize' => $env['cli-memory'] ?? 1024,
            'layers' => $layers,
            'events' => [[
                'schedule' => [
                    'rate' => 'rate(1 minute)',
                    'enabled' => true,
                    'input' => [
                        'cli' => 'schedule:run',
                    ],
                ],
            ]],
        ];

        if (isset($env['schedule'], $env['schedule']['events']) && is_array($env['schedule']['events'])) {
            $schedule['events'] = array_merge($schedule['events'], $env['schedule']['events']);
        }

        $yaml['plugins'] = [
            'serverless-deployment-bucket',
        ];

        if ($queues !== false) {
            foreach ($queues as $queue_name) {
                $name = Str::studly($queue_name);
                $resources[$name.'Queue'] = [
                    'Type' => 'AWS::SQS::Queue',
                    'Properties' => [
                        'QueueName' => $queue_name,
                        'VisibilityTimeout' => $env['queue-timeout'] ?? ($env['timeout'] ?? 60),
                        'RedrivePolicy' => [
                            'maxReceiveCount' => 3,
                            'deadLetterTargetArn' => '!GetAtt '.$name.'FailedQueue.Arn',
                        ],
                    ],
                ];

                $resources[$name.'FailedQueue'] = [
                    'Type' => 'AWS::SQS::Queue',
                    'Properties' => [
                        'QueueName' => $queue_name.'_failed',
                        'VisibilityTimeout' => $env['queue-timeout'] ?? ($env['timeout'] ?? 60),
                        'MessageRetentionPeriod' => (7 * 24 * 60),
                    ],
                ];
            }
        }

        if (isset($environment['CACHE_STORE'])
            && $environment['CACHE_STORE'] == 'dynamodb'
        && (! isset($env['cache-table']) || $env['cache-table'] !== false)
        ) {
            $resources['cacheTable'] = [
                'Type' => 'AWS::DynamoDB::Table',
                'Properties' => [
                    'TableName' => $env['cache-table'] ?? 'cache',
                    'AttributeDefinitions' => [[
                        'AttributeName' => 'key',
                        'AttributeType' => 'S',
                    ]],
                    'KeySchema' => [[
                        'AttributeName' => 'key',
                        'KeyType' => 'HASH',
                    ]],
                    'BillingMode' => 'PAY_PER_REQUEST',
                ],
            ];

            if (isset($env['autoscale']) && $env['autoscale'] !== false) {
                $yaml['custom']['capacities'] = [[
                    'table' => 'cacheTable',
                    'read' => [
                        'minimum' => 1,
                        'maximum' => 1000,
                        'usage' => 0.75,
                    ],
                    'write' => [
                        'minimum' => 40,
                        'maximum' => 200,
                        'usage' => 0.5,
                    ],
                ]];

                \array_push($yaml['plugins'], 'serverless-dynamodb-autoscaling');
            }
        }

        if (isset($environment['SESSION_DRIVER'])
        && $environment['SESSION_DRIVER'] == 'dynamodb'
            && (! isset($env['session-table']) || $env['session-table'] !== false)
        ) {
            $resources['sessionTable'] = [
                'Type' => 'AWS::DynamoDB::Table',
                'Properties' => [
                    'TableName' => $env['session-table'] ?? 'sessions',
                    'AttributeDefinitions' => [[
                        'AttributeName' => 'id',
                        'AttributeType' => 'S',
                    ]],
                    'KeySchema' => [[
                        'AttributeName' => 'id',
                        'KeyType' => 'HASH',
                    ]],
                    'BillingMode' => 'PAY_PER_REQUEST',
                ],
            ];

            if (isset($env['autoscale']) && $env['autoscale'] !== false) {
                $yaml['custom']['capacities'] = [[
                    'table' => 'sessionTable',
                    'read' => [
                        'minimum' => 1,
                        'maximum' => 1000,
                        'usage' => 0.75,
                    ],
                    'write' => [
                        'minimum' => 40,
                        'maximum' => 200,
                        'usage' => 0.5,
                    ],
                ]];

                \array_push($yaml['plugins'], 'serverless-dynamodb-autoscaling');
            }
        }

        if (isset($bucket)) {
            $yaml['custom']['s3Sync'] = [[
                'bucketName' => $bucket,
                'bucketPrefix' => $bucket_prefix,
                'localDir' => 'assets',
                // 'acl' => 'public-read',
            ]];

            if (isset($env['asset-bucket']) && $env['asset-bucket'] !== false) {
                $resources = array_merge((new AssetsBucket)->getResources($bucket), $resources);
            }

            \array_push($yaml['plugins'], 'serverless-s3-sync');
        }

        if (isset($env['domain']) && $env['domain'] !== false) {
            $domain = $env['domain'];

            if (\substr($domain, 0, 2) == '*.') {
                $domain = '${opt:RANDOM_STRING}.'.\substr($domain, 2, -1);
            }

            $domain = \str_replace('*', '${opt:RANDOM_STRING}', $domain);

            $yaml['custom']['customDomain'] = \array_filter([
                'domainName' => $domain,
                'stage' => '${self:provider.stage}',
                'createRoute53Record' => 'true',
                'autoDomain' => 'true',
                'endpointType' => $env['endpoint'] ?? 'regional',
                'certificateName' => $env['certificate'] ?? null,
            ]);

            \array_push($yaml['plugins'], 'serverless-domain-manager');
        }

        $fs = null;
        if (isset($env['volumes']) && \count($env['volumes']) > 0) {
            foreach ($env['volumes'] as $volume) {
                [$local, $access_point] = \explode(':', $volume, 2);

                if (! empty($access_point) && \substr($access_point, 0, 4) != 'arn:') {
                    $access_point = 'arn:aws:elasticfilesystem:${self:provider.region}:'.$account_id.':access-point/'.$access_point;
                }

                if (! empty($local) && ! empty($access_point)) {
                    $fs = [
                        'localMountPath' => $local,
                        'arn' => $access_point,
                    ];
                }
            }
        }

        if (! isset($env['web']) || $env['web'] !== false) {
            if ($docker) {
                if (! empty($env['image'])) {
                    $web['image'] = $env['image'];
                } else {
                    $web['image'] = [
                        'name' => $image,
                        'workingDirectory' => $env['working-dir'] ?? '/var/task',
                        'command' => $env['cmd'] ?? null,
                        'entryPoint' => $env['entry-point'] ?? null,
                    ];
                }

                unset($web['handler'], $web['layers']);
            }

            if (! empty($fs) && \is_array($fs)) {
                $web['fileSystemConfig'] = $fs;
            }

            if (! empty($env['size']) && is_numeric($env['size'])) {
                $web['ephemeralStorageSize'] = $env['size'];
            }

            if (! empty($env['kms'])) {
                $web['awsKmsKeyArn'] = (\substr($env['kms'], 0, 4) != 'arn:') ? 'arn:aws:kms:${self:provider.region}:'.$account_id.':key/'.$env['kms'] : $env['kms'];
            }

            $yaml['functions']['web'] = \array_filter($web);
        }

        if (isset($env['queues']) && $queues !== false) {
            if ($docker) {
                if (! empty($env['image'])) {
                    $queue['image'] = $env['image'];
                } else {
                    $queue['image'] = [
                        'name' => $image,
                        'workingDirectory' => $env['working-dir'] ?? '/var/task',
                        'command' => $env['cmd'] ?? null,
                        'entryPoint' => $env['entry-point'] ?? null,
                    ];
                }

                unset($queue['handler'], $queue['layers']);
            }

            if (! empty($fs) && \is_array($fs)) {
                $queue['fileSystemConfig'] = $fs;
            }

            if (! empty($env['size']) && is_numeric($env['size'])) {
                $queue['ephemeralStorageSize'] = $env['size'];
            }

            if (! empty($env['kms'])) {
                $queue['awsKmsKeyArn'] = (\substr($env['kms'], 0, 4) != 'arn:') ? 'arn:aws:kms:${self:provider.region}:'.$account_id.':key/'.$env['kms'] : $env['kms'];
            }

            foreach ($queues as $queue_name) {
                $queue['events'][]['sqs'] = [
                    'arn' => '!GetAtt '.Str::studly($queue_name).'Queue.Arn',
                    'batchSize' => $env['queue-size'] ?? 1,
                ];
            }

            $yaml['functions']['queue'] = \array_filter($queue);
        }

        if (isset($env['scheduler']) && $env['scheduler'] !== false) {
            if ($docker) {
                if (! empty($env['image'])) {
                    $schedule['image'] = $env['image'];
                } else {
                    $schedule['image'] = [
                        'name' => $image,
                        'workingDirectory' => $env['working-dir'] ?? '/var/task',
                        'command' => $env['cmd'] ?? null,
                        'entryPoint' => $env['entry-point'] ?? null,
                    ];
                }

                unset($schedule['handler'], $schedule['layers']);
            }

            if (! empty($fs) && \is_array($fs)) {
                $schedule['fileSystemConfig'] = $fs;
            }

            if (! empty($env['size']) && is_numeric($env['size'])) {
                $schedule['ephemeralStorageSize'] = $env['size'];
            }

            if (! empty($env['kms'])) {
                $schedule['awsKmsKeyArn'] = (\substr($env['kms'], 0, 4) != 'arn:') ? 'arn:aws:kms:${self:provider.region}:'.$account_id.':key/'.$env['kms'] : $env['kms'];
            }

            $yaml['functions']['schedule'] = \array_filter($schedule);
        }

        $yaml['resources'] = ['Resources' => $resources];

        static::write(\array_filter($yaml));

        // Generate CDK project if it doesn't exist
        if (! file_exists(self::cdkAppPath())) {
            self::initCdkProject($name, $region);
        }

        // Generate CDK stack file
        self::generateCdkStack($manifest, $stage, $env, $region, $name, $runtime, $image, $layers, $uuid, $account_id, $queues);
    }

    /**
     * Initialize a new CDK project.
     *
     * @param  string  $name
     * @param  string  $region
     */
    protected static function initCdkProject($name, $region): void
    {
        Helpers::step('<comment>Initializing CDK project</comment>');

        // Create cdk directory if it doesn't exist
        if (! is_dir(Path::build().'/cdk')) {
            mkdir(Path::build().'/cdk', 0755, true);
        }

        // Create package.json
        $packageJson = file_get_contents(__DIR__.'/Cdk/Templates/package.json.stub');
        $packageJson = str_replace(['{{name}}', '{{region}}'], [$name, $region], $packageJson);
        file_put_contents(Path::build().'/cdk/package.json', $packageJson);

        // Create tsconfig.json
        $tsConfig = file_get_contents(__DIR__.'/Cdk/Templates/tsconfig.json.stub');
        file_put_contents(Path::build().'/cdk/tsconfig.json', $tsConfig);

        // Create cdk.json
        $cdkJson = file_get_contents(__DIR__.'/Cdk/Templates/cdk.json.stub');
        file_put_contents(Path::build().'/cdk/cdk.json', $cdkJson);

        // Create app.ts
        $appTs = file_get_contents(__DIR__.'/Cdk/Templates/cdk-app.ts.stub');
        $appTs = str_replace('{{name}}', $name, $appTs);
        file_put_contents(Path::build().'/cdk/app.ts', $appTs);

        // Install dependencies
        $process = Process::fromShellCommandline('npm install', Path::build().'/cdk');
        $process->setTimeout(300);
        $process->mustRun(function ($type, $line): void {
            Helpers::write($line);
        });
    }

    /**
     * Generate the CDK stack for the given environment.
     *
     * @param  array  $manifest
     * @param  string  $stage
     * @param  array  $env
     * @param  string  $region
     * @param  string  $name
     * @param  string  $runtime
     * @param  string  $image
     * @param  array|null  $layers
     * @param  string  $uuid
     * @param  string  $account_id
     * @param  array|bool  $queues
     */
    protected static function generateCdkStack($manifest, $stage, $env, $region, $name, $runtime, $image, $layers, $uuid, $account_id, $queues): void
    {
        $stackName = Str::studly($name).Str::studly($stage).'Stack';
        $stackFile = Path::build()."/cdk/lib/{$stackName}.ts";

        // Create lib directory if it doesn't exist
        if (! is_dir(Path::build().'/cdk/lib')) {
            mkdir(Path::build().'/cdk/lib', 0755, true);
        }

        // Prepare environment variables
        $secrets = [];
        if (! empty($env['copy-env'])) {
            $docker = $runtime == 'docker' || $runtime == 'docker-arm';
            if ($docker) {
                $secrets = static::getProjectEnv(Path::app(), $stage, '.env.docker');
            }

            if (empty($secrets)) {
                $secrets = static::getProjectEnv(Path::app(), $stage, '.env');
            }
        }

        $environment = \array_merge([
            'VAPOR_SSM_PATH' => $env['ssm'] ?? '/'.$manifest['org'].'/'.$stage.'/'.$name,
            'VAPOR_SSM_VARIABLES' => '[]',
            'VAPOR_SERVERLESS_DB' => 'false',
            'VAPOR_MAINTENANCE_MODE' => 'false',
            'VAPOR_MAINTENANCE_MODE_SECRET' => 'secret',
            'VAPOR_ENVIRONMENT' => $stage,
            'VAPOR_PROJECT' => $name,
            'LOG_CHANNEL' => 'stderr',
            'LOG_STDERR_FORMATTER' => 'Laravel\Vapor\Logging\JsonFormatter',
            'APP_CONFIG_CACHE' => '/tmp/storage/bootstrap/cache/config.php',
            'LD_LIBRARY_PATH' => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
            'PATH' => '/opt/bin:/usr/local/bin:/usr/bin/:/bin',
            'XDG_CONFIG_HOME' => '/tmp',
            'APP_VANITY_URL' => '',
        ], $secrets);

        // Get the template and fill in the variables
        $stackTemplate = file_get_contents(__DIR__.'/Cdk/Templates/stack.ts.stub');

        // Replace placeholders with actual values
        $replacements = [
            '{{stackName}}' => $stackName,
            '{{name}}' => $name,
            '{{stage}}' => $stage,
            '{{region}}' => $region,
            '{{account}}' => $account_id,
            '{{runtime}}' => $runtime,
            '{{memory}}' => $env['memory'] ?? '1024',
            '{{timeout}}' => $env['timeout'] ?? '60',
        ];

        $stackContent = str_replace(array_keys($replacements), array_values($replacements), $stackTemplate);

        // Add environment variables
        $envVars = "{\n";
        foreach ($environment as $key => $value) {
            $envVars .= "        '$key': '$value',\n";
        }
        $envVars .= '      }';
        $stackContent = str_replace('{{environment}}', $envVars, $stackContent);

        // Write the stack file
        file_put_contents($stackFile, $stackContent);

        // Update app.ts to include the new stack
        $appPath = Path::build().'/cdk/app.ts';
        $appContent = file_get_contents($appPath);

        // Check if stack import already exists
        if (! str_contains($appContent, "import { $stackName } from './lib/$stackName'")) {
            $importLine = "import { $stackName } from './lib/$stackName';\n";
            $appContent = preg_replace('/(import.+;\n)(?!import)/', "$1$importLine", $appContent, 1);

            // Add stack instantiation if it doesn't exist
            if (! str_contains($appContent, "new $stackName(app, '$name-$stage'")) {
                $stackLine = "new $stackName(app, '$name-$stage', {\n  env: { account: '$account_id', region: '$region' },\n});\n";
                $appContent = preg_replace('/};\s*$/', "  $stackLine};\n", $appContent);
            }

            file_put_contents($appPath, $appContent);
        }
    }

    /**
     * Get the path to the CDK app file.
     */
    public static function cdkAppPath(): string
    {
        return Path::build().'/cdk/app.ts';
    }

    /**
     * Deploy the CDK stack for the given environment.
     *
     * @param  string  $stage
     * @param  string|null  $extra
     */
    public static function deploy($stage, $extra = null): void
    {
        Helpers::step('<comment>Synthesizing CDK Stack</comment>');

        $process = Process::fromShellCommandline('npx cdk synth', Path::build().'/cdk');
        $process->setTimeout(300);
        $process->mustRun(function ($type, $line): void {
            Helpers::write($line);
        });

        Helpers::step('<comment>Deploying CDK Stack</comment>');

        $command = 'npx cdk deploy --require-approval never';
        if ($extra) {
            $command .= ' '.$extra;
        }

        $process = Process::fromShellCommandline($command, Path::build().'/cdk');
        $process->setTimeout(10 * 60 * 60);
        $process->mustRun(function ($type, $line): void {
            Helpers::write($line);
        });
    }

    /**
     * Write the given array to disk as the new manifest.
     *
     * @param  null|string  $path
     */
    protected static function write(array $manifest, $path = null): void
    {
        $yaml = Yaml::dump($manifest, 20, 2);
        $yaml = \preg_replace("/'(![^']+)'/", '$1', $yaml);

        \file_put_contents($path ?: static::manifest(), $yaml);
    }

    protected static function toLayers($runtime, $region)
    {
        if (empty($runtime) || empty($region)) {
            return [];
        }

        $layers = static::layers();
        $runtime = \str_replace('.', '', $runtime);
        $layer = isset($layers[$runtime]) ? $layers[$runtime] : [];
        $layer = isset($layer[$region]) ? $layer[$region] : null;

        return ! \is_array($layer) ? [$layer] : $layer;
    }

    protected static function layers()
    {
        if (\file_exists($file = __DIR__.'/../layers.json')) {
            return \json_decode(\file_get_contents($file), true);
        }

        return [];
    }

    protected static function envVarsToArray($environments)
    {
        if (! \is_array($environments)) {
            return [];
        }

        $variables = [];
        foreach ($environments as $environment) {
            if (\strpos($environment, '=') !== false) {
                [$key, $value] = \explode('=', $environment, 2);
                $variables[$key] = $value;
            }
        }

        return $variables;
    }

    protected static function createTargetGroup($name, $data = [])
    {
        $health = $data['health'] ?? [];

        return [
            'Type' => 'AWS::ElasticLoadBalancingV2::TargetGroup',
            'Properties' => [
                'TargetType' => 'lambda',
                'Targets' => [['Id' => ['Fn::GetAtt' => [Str::studly($name).'LambdaFunction', 'Arn']]]],
                'Name' => $data['name'],
                'Tags' => [
                    [
                        'Key' => 'Name',
                        'Value' => $data['name'],
                    ],
                ],
                'TargetGroupAttributes' => [
                    [
                        'Key' => 'lambda.multi_value_headers.enabled',
                        'Value' => true,
                    ],
                ],
                'HealthCheckEnabled' => isset($health['path']) ? true : false,
                'HealthCheckPath' => $health['path'] ?? '',
                'HealthCheckIntervalSeconds' => $health['interval'] ?? 35,
                'HealthCheckTimeoutSeconds' => $health['timeout'] ?? 30,
                'HealthyThresholdCount' => $health['count'] ?? 5,
                'UnhealthyThresholdCount' => $health['count'] ?? 5,
                'Matcher' => ['HttpCode' => '200'],
            ],
        ];
    }

    /**
     * Format the Docker CLI build arguments.
     *
     * @return array<int, string>
     */
    public static function formatBuildArguments($environment)
    {
        $cliBuildArgs = array_merge(
            ['__VAPOR_RUNTIME='.Manifest::runtime($environment)],
            array_filter(Manifest::dockerBuildArgs($environment), function ($value) {
                return ! Str::startsWith($value, '__VAPOR_RUNTIME');
            })
        );

        return Collection::make($cliBuildArgs)
            ->mapWithKeys(function ($value) {
                [$key, $value] = explode('=', $value, 2);

                return [$key => $value];
            })->toArray();

    }

    public static function formatBuildOptions($environment)
    {
        $cliBuildOptions = array_filter(Manifest::dockerBuildOptions($environment), function ($value) {
            return ! Str::startsWith($value, 'platform');
        });

        $cliBuildOptions = Collection::make($cliBuildOptions)
            ->mapWithKeys(function ($value) {
                if (! str_contains($value, '=')) {
                    return [$value => null];
                }

                [$key, $value] = explode('=', $value, 2);

                return [$key => $value];
            });

        $options = [];
        foreach ($cliBuildOptions as $key => $value) {
            $options[] = '--'.$key;
            $options[] = $value;
        }

        return $options;
    }
}

<?php

namespace Diviky\Serverless;

use Diviky\Serverless\Concerns\EnvReader;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Serverless
{
    use EnvReader;

    /**
     * Write a fresh main manifest file for the given environment.
     *
     * @param string $stage
     */
    public static function generate($stage)
    {
        $manifest = Manifest::current();

        $env           = $manifest['environments'][$stage];
        $region        = $manifest['region'] ?? 'ap-south-1';
        $name          = $manifest['name'];
        $runtime       = $env['runtime'];
        $image         = $manifest['name'];
        $layers        = null;
        $uuid          = \date('ymdhm');

        $env['layers'] = $env['layers'] ?? [];

        $queue_name    = $stage . '_default';
        $cache         = $name . '_' . $stage . '_cache';

        $docker = 'dockerize' == $runtime || 'docker' == $runtime;

        if ($docker) {
            $layers  = self::toLayers($runtime, $region);
            $layers  = \array_filter(\array_merge($layers, $env['layers']));
        }

        //arn:aws:lambda:ap-south-1:959512994844:layer:vapor-php-74:11

        $yaml = [
            'org'     => $manifest['org'] ?? $name,
            'service' => $name,
        ];

        $secrets = [];

        if ($docker) {
            $secrets = static::getProjectEnv(Path::app(), $stage, '.env.docker');
        }

        if (empty($secrets)) {
            $secrets = static::getProjectEnv(Path::app(), $stage, '.env');
        }

        $environment = \array_merge([
            'VAPOR_SSM_PATH'                => '/' . $name,
            'VAPOR_SSM_VARIABLES'           => '[]',
            'VAPOR_SERVERLESS_DB'           => 'false',
            'VAPOR_MAINTENANCE_MODE'        => 'false',
            'VAPOR_MAINTENANCE_MODE_SECRET' => 'secret',
            'VAPOR_ENVIRONMENT'             => $stage,
            'VAPOR_PROJECT'                 => $name,
            'LOG_CHANNEL'                   => 'stderr',
            'LOG_STDERR_FORMATTER'          => 'Laravel\Vapor\Logging\JsonFormatter',
            'APP_CONFIG_CACHE'              => '/tmp/storage/bootstrap/cache/config.php',
            'LD_LIBRARY_PATH'               => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
            'PATH'                          => '/opt/bin:/usr/local/bin:/usr/bin/:/bin',
            'XDG_CONFIG_HOME'               => '/tmp',
            'APP_VANITY_URL'                => '',
        ], $secrets);

        $environment = \array_merge([
            'QUEUE_CONNECTION'              => 'sqs',
            'SESSION_DRIVER'                => 'cookie',
            'CACHE_DRIVER'                  => 'dynamodb',
            'DYNAMODB_CACHE_TABLE'          => $cache,
            'SQS_QUEUE'                     => $queue_name,
        ], $environment);

        $bucket        = null;
        $bucket_prefix = null;
        if (isset($env['assets']) && false !== $env['assets']) {
            $bucket                   = \is_string($env['assets']) ? $env['assets'] : 'com.${self:org}.${self:provider.region}.assets';
            $bucket_prefix            = $stage . '/' . $uuid;
            $environment['MIX_URL']   = 'https://${self:provider.region}.amazonaws.com/' . $bucket . '/' . $bucket_prefix;
            $environment['ASSET_URL'] = 'https://${self:provider.region}.amazonaws.com/' . $bucket . '/' . $bucket_prefix;
        }

        $environment = \array_merge($environment, $env['environment'] ?? []);

        $yaml['provider'] = \array_filter([
            'name'              => 'aws',
            'region'            => $region,
            'stage'             => $stage,
            'runtime'           => 'provided',
            'environment'       => $environment,
            'apiGateway'        => [
                'shouldStartNameWithService' => true,
            ],
            'deploymentBucket'  => [
                'name'                           => 'com.${self:org}.${self:provider.region}.deploys',
                'maxPreviousDeploymentArtifacts' => 10,
                'blockPublicAccess'              => true,
            ],
            'iam' => [
                'role' => [
                    'statements' => [[
                        'Effect'   => 'Allow',
                        'Action'   => [
                            'dynamodb:*',
                            's3:*',
                            'ses:*',
                            'sqs:*',
                            'kms:Decrypt',
                            'secretsmanager:GetSecretValue',
                            'ssm:GetParameters',
                            'ssm:GetParameter',
                            'lambda:invokeFunction',
                        ],
                        'Resource' => '*',
                    ]],
                ],
            ],
            'vpc'               => \array_filter([
                'subnetIds'        => $env['subnets'] ?? null,
                'securityGroupIds' => $env['security-groups'] ?? null,
            ]),
        ]);

        if ($docker) {
            $yaml['provider']['ecr'] = [
                'images' => [
                    $image => [
                        'path' => Path::app(),
                        'file' => $stage . '.Dockerfile',
                    ],
                ],
            ];
        } else {
            $yaml['package'] = [
                'artifact' => 'app.zip',
            ];
        }

        $yaml['functions'] = [];
        $yaml['resources'] = [];
        $yaml['custom']    = [];

        $web = [
            'handler'                => 'vaporHandler.handle',
            'timeout'                => $env['timeout'] ?? 28,
            'memorySize'             => $env['memory'] ?? 1024,
            'reservedConcurrency'    => $env['concurrency'] ?? null,
            'provisionedConcurrency' => $env['capacity'] ?? null,
            'layers'                 => $layers,
            'events'                 => [
                ['http' => 'ANY /'],
                ['http' => 'ANY /{proxy+}'],
                ['schedule' => [
                    'enabled' => true,
                    'rate'    => 'rate(5 minutes)',
                    'input'   => [
                        'vaporWarmer'     => true,
                        'vaporWarmerPing' => true,
                        'concurrency'     => $env['warm'] ?? 10,
                    ],
                ]],
            ],
        ];

        $queue = [
            'handler'             => 'vaporHandler.handle',
            'environment'         => [
                'APP_RUNNING_IN_CONSOLE' => 'true',
            ],
            'timeout'                => $env['queue-timeout'] ?? null,
            'memorySize'             => $env['queue-memory'] ?? null,
            'reservedConcurrency'    => $env['queue-concurrency'] ?? null,
            'layers'                 => $layers,
            'events'                 => [[
                'sqs' => [
                    'arn'       => '!GetAtt Queues.Arn',
                    'batchSize' => 1,
                ],
            ]],
        ];

        $schedule = [
            'handler'     => 'vaporHandler.handle',
            'environment' => [
                'APP_RUNNING_IN_CONSOLE' => 'true',
            ],
            'timeout'    => $env['cli-timeout'] ?? null,
            'memorySize' => $env['cli-memory'] ?? 1024,
            'layers'     => $layers,
            'events'     => [[
                'schedule' => [
                    'rate'    => 'rate(1 minute)',
                    'enabled' => true,
                ],
            ]],
        ];

        $yaml['plugins'] = [
            'serverless-deployment-bucket',
            'serverless-dynamodb-autoscaling',
            'serverless-s3-sync',
            'serverless-plugin-scripts',
        ];

        $resources = [];

        if (false !== $env['queues']) {
            $resources['Queues'] = [
                'Type'       => 'AWS::SQS::Queue',
                'Properties' => [
                    'QueueName'     => $queue_name,
                    'RedrivePolicy' => [
                        'maxReceiveCount'     => 3,
                        'deadLetterTargetArn' => '!GetAtt FailedQueues.Arn',
                    ],
                ],
            ];

            $resources['FailedQueues'] = [
                'Type'       => 'AWS::SQS::Queue',
                'Properties' => [
                    'Type'       => 'AWS::SQS::Queue',
                    'Properties' => [
                        'QueueName'              => $queue_name . '_failed',
                        'MessageRetentionPeriod' => (7 * 24 * 60),
                    ],
                ],
            ];
        }

        if (isset($environment['CACHE_DRIVER']) && 'dynamodb' == $environment['CACHE_DRIVER']) {
            $resources['cacheTable'] = [
                'Type'       => 'AWS::DynamoDB::Table',
                'Properties' => [
                    'TableName'             => $cache,
                    'AttributeDefinitions'  => [[
                        'AttributeName' => 'key',
                        'AttributeType' => 'S',
                    ]],
                    'KeySchema'             => [[
                        'AttributeName' => 'key',
                        'KeyType'       => 'HASH',
                    ]],
                    'BillingMode' => 'PAY_PER_REQUEST',
                ],
            ];

            if (isset($env['autoscale']) && false !== $env['autoscale']) {
                $yaml['custom']['capacities'] = [[
                    'table' => 'cacheTable',
                    'read'  => [
                        'minimum' => 1,
                        'maximum' => 1000,
                        'usage'   => 0.75,
                    ],
                    'write' => [
                        'minimum' => 40,
                        'maximum' => 200,
                        'usage'   => 0.5,
                    ],
                ]];
            }
        }

        if (isset($bucket)) {
            $yaml['custom']['s3Sync'] = [[
                'bucketName'   => $bucket,
                'bucketPrefix' => $bucket_prefix,
                'localDir'     => 'assets',
                'acl'          => 'public-read',
            ]];

            $resources['AssetsBucket'] = [
                'Type'       => 'AWS::S3::Bucket',
                'Properties' => [
                    'BucketName'    => $bucket,
                    'AccessControl' => 'PublicRead',
                ],
            ];
        }

        $yaml['resources'] = ['Resources' => $resources];

        if (!isset($env['web']) || false !== $env['web']) {
            if ($docker) {
                $web['image'] = [
                    'name' => $image,
                ];

                unset($web['handler'], $web['layers']);
            }

            $yaml['functions']['web'] = \array_filter($web);
        }

        if (isset($env['queues']) && false !== $env['queues']) {
            if ($docker) {
                $queue['image'] = [
                    'name' => $image,
                ];

                unset($queue['handler'], $queue['layers']);
            }
            $yaml['functions']['queue'] = \array_filter($queue);
        }

        if (isset($env['scheduler']) && false !== $env['scheduler']) {
            if ($docker) {
                $schedule['image'] = [
                    'name' => $image,
                ];

                unset($schedule['handler'], $schedule['layers']);
            }
            $yaml['functions']['schedule'] = \array_filter($schedule);
        }

        static::write(\array_filter($yaml));
    }

    public static function manifest()
    {
        return Path::build() . '/serverless.yml';
    }

    public static function deploy($extra = null)
    {
        Helpers::step('<comment>Executing Serverless Commands</comment>');

        $command = \trim('serverless deploy ' . $extra);
        $process = Process::fromShellCommandline($command, Path::build());
        $process->setTimeout(10 * 60 * 60);

        $process->mustRun(function ($type, $line) {
            $line = \str_replace('Serverless: ', '', $line);
            Helpers::write($line);
        });
    }

    /**
     * Write the given array to disk as the new manifest.
     *
     * @param null|string $path
     */
    protected static function write(array $manifest, $path = null)
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

        $layers   = static::layers();
        $runtime  = \str_replace('.', '', $runtime);
        $layer    = isset($layers[$runtime]) ? $layers[$runtime] : [];
        $layer    = isset($layer[$region]) ? $layer[$region] : null;

        return !\is_array($layer) ? [$layer] : $layer;
    }

    protected static function layers()
    {
        if (\file_exists($file = __DIR__ . '/../layers.json')) {
            return \json_decode(\file_get_contents($file), true);
        }

        return [];
    }
}

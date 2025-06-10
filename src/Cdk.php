<?php

declare(strict_types=1);

namespace Diviky\Serverless;

use Diviky\Serverless\Concerns\EnvReader;
use Illuminate\Support\Str;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Process\Process;

class Cdk
{
    use EnvReader;

    /**
     * Generate the CDK stack for the given environment.
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
        $account_id = $manifest['id'];
        $uuid = \date('ymdhm');

        // Handle queue configuration
        $queue_name = $stage.'_default';
        $queues = $env['queues'] ?? false;

        if ($queues !== false) {
            $queues = $queues === true ? [$queue_name] : $queues;
            $queues = ! is_array($queues) ? [$queues] : $queues;
        }

        // Prepare environment variables
        $secrets = [];
        $docker = $runtime == 'docker' || $runtime == 'docker-arm';

        if (! empty($env['copy-env'])) {
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

        // Handle asset bucket
        $bucket = null;
        $bucket_prefix = null;
        if (isset($env['assets']) && $env['assets'] !== false) {
            $bucket = \is_string($env['assets']) ? $env['assets'] : 'com.'.$manifest['org'].'.'.$region.'.assets';
            $bucket_prefix = $stage.'/'.$uuid;

            if (isset($env['asset-domain']) && is_string($env['asset-domain'])) {
                $assets = Str::beforeLast($env['asset-domain'], '/').'/'.$bucket_prefix;
            } else {
                $assets = 'https://s3.'.$region.'.amazonaws.com/'.$bucket.'/'.$bucket_prefix;
            }

            $environment['ASSET_URL'] = $assets;
            $environment['MIX_URL'] = $assets;
        }

        // Add custom environment variables
        $environment = \array_merge($environment, self::envVarsToArray($env['environment'] ?? null));

        // Generate CDK project if it doesn't exist
        if (! file_exists(self::cdkAppPath())) {
            self::initCdkProject($name, $region);
        }

        // Generate the CDK stack file
        self::generateCdkStack(
            manifest: $manifest,
            stage: $stage,
            env: $env,
            region: $region,
            name: $name,
            runtime: $runtime,
            queues: $queues,
            environment: $environment,
            bucket: $bucket,
            bucket_prefix: $bucket_prefix,
            account_id: $account_id
        );
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

        // Create directories for modular components
        $dirs = [
            Path::build().'/cdk/lib',
            Path::build().'/cdk/lib/resources',
            Path::build().'/cdk/lib/functions',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create base resource files from templates
        $resources = [
            'queue', 'dynamodb', 's3', 'domain',
        ];

        foreach ($resources as $resource) {
            $template = file_get_contents(__DIR__.'/Cdk/Templates/resources/'.$resource.'.ts.stub');
            $resourcePath = Path::build().'/cdk/lib/resources/'.$resource.'.ts';
            if (! file_exists($resourcePath)) {
                file_put_contents($resourcePath, $template);
            }
        }

        // Create base function files from templates
        $functions = [
            'web', 'queue', 'schedule',
        ];

        foreach ($functions as $function) {
            $template = file_get_contents(__DIR__.'/Cdk/Templates/functions/'.$function.'.ts.stub');
            $functionPath = Path::build().'/cdk/lib/functions/'.$function.'.ts';
            if (! file_exists($functionPath)) {
                file_put_contents($functionPath, $template);
            }
        }
    }

    /**
     * Generate the CDK stack for the given environment.
     */
    protected static function generateCdkStack(
        array $manifest,
        string $stage,
        array $env,
        string $region,
        string $name,
        string $runtime,
        array|bool $queues,
        array $environment,
        ?string $bucket = null,
        ?string $bucket_prefix = null,
        ?string $account_id = null
    ): void {
        $stackName = Str::studly($name).Str::studly($stage).'Stack';
        $stackFile = Path::build()."/cdk/lib/{$stackName}.ts";

        // Create lib directory if it doesn't exist
        if (! is_dir(Path::build().'/cdk/lib')) {
            mkdir(Path::build().'/cdk/lib', 0755, true);
        }

        // Get the template and fill in the variables
        $stackTemplate = file_get_contents(__DIR__.'/Cdk/Templates/stack.ts.stub');

        // Queue settings
        $queueEnabled = $queues !== false;
        $queueNames = json_encode($queueEnabled ? $queues : []);
        $queueBatchSize = $env['queue-size'] ?? 1;

        // DynamoDB cache/session settings
        $cacheEnabled = isset($environment['CACHE_STORE']) && $environment['CACHE_STORE'] == 'dynamodb' && (! isset($env['cache-table']) || $env['cache-table'] !== false);
        $cacheName = $env['cache-table'] ?? 'cache';

        $sessionEnabled = isset($environment['SESSION_DRIVER']) && $environment['SESSION_DRIVER'] == 'dynamodb' && (! isset($env['session-table']) || $env['session-table'] !== false);
        $sessionName = $env['session-table'] ?? 'sessions';

        // Asset bucket settings
        $assetEnabled = ! empty($bucket);
        $assetBucket = $bucket ?? '';
        $assetPrefix = $bucket_prefix ?? '';

        // Domain settings
        $domainEnabled = isset($env['domain']) && $env['domain'] !== false;
        $domainName = $env['domain'] ?? '';
        $certificateArn = $env['certificate'] ?? '';
        $hostedZoneId = $env['hosted-zone-id'] ?? '';
        $hostedZoneName = $env['hosted-zone-name'] ?? '';

        // Schedule settings
        $schedulerEnabled = isset($env['scheduler']) && $env['scheduler'] !== false;

        // Warmer settings
        $warmerConcurrency = $env['warm'] ?? 10;

        // Replace placeholders with actual values
        $replacements = [
            '{{stackName}}' => $stackName,
            '{{name}}' => $name,
            '{{stage}}' => $stage,
            '{{region}}' => $region,
            '{{account}}' => $account_id ?? '',
            '{{runtime}}' => $runtime,
            '{{memory}}' => $env['memory'] ?? '1024',
            '{{timeout}}' => $env['timeout'] ?? '60',
            '{{queueMemory}}' => $env['queue-memory'] ?? $env['memory'] ?? '1024',
            '{{queueTimeout}}' => $env['queue-timeout'] ?? $env['timeout'] ?? '60',
            '{{scheduleMemory}}' => $env['cli-memory'] ?? $env['memory'] ?? '1024',
            '{{scheduleTimeout}}' => $env['cli-timeout'] ?? $env['timeout'] ?? '60',
            '{{queueEnabled}}' => $queueEnabled ? 'true' : 'false',
            '{{queueNames}}' => $queueNames,
            '{{queueBatchSize}}' => $queueBatchSize,
            '{{cacheEnabled}}' => $cacheEnabled ? 'true' : 'false',
            '{{cacheName}}' => $cacheName,
            '{{sessionEnabled}}' => $sessionEnabled ? 'true' : 'false',
            '{{sessionName}}' => $sessionName,
            '{{assetEnabled}}' => $assetEnabled ? 'true' : 'false',
            '{{assetBucket}}' => $assetBucket,
            '{{assetPrefix}}' => $assetPrefix,
            '{{domainEnabled}}' => $domainEnabled ? 'true' : 'false',
            '{{domainName}}' => $domainName,
            '{{certificateArn}}' => $certificateArn,
            '{{hostedZoneId}}' => $hostedZoneId,
            '{{hostedZoneName}}' => $hostedZoneName,
            '{{schedulerEnabled}}' => $schedulerEnabled ? 'true' : 'false',
            '{{warmerConcurrency}}' => $warmerConcurrency,
        ];

        $stackContent = str_replace(array_keys($replacements), array_values($replacements), $stackTemplate);

        // Add environment variables
        $envVars = "{\n";
        foreach ($environment as $key => $value) {
            $value = addslashes($value);
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
     * Convert environment string array to associative array.
     */
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
}

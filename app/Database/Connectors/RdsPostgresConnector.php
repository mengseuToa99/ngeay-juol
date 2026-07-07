<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\Facades\Cache;

class RdsPostgresConnector extends PostgresConnector
{
    /**
     * In-memory cache for the current request context/process.
     *
     * @var array
     */
    protected static $tokenCache = [];

    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        if (isset($config['rds_iam']) && $config['rds_iam']) {
            $config['password'] = $this->generateRdsToken($config);
        }

        return parent::connect($config);
    }

    /**
     * Generate a temporary RDS database authentication token.
     *
     * @param  array  $config
     * @return string
     */
    protected function generateRdsToken(array $config)
    {
        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 5432;
        $username = $config['username'] ?? 'postgres';
        $region = env('AWS_DEFAULT_REGION', 'ap-southeast-2');

        $cacheKey = 'rds_db_token_' . md5("{$host}:{$port}:{$username}:{$region}");

        // 1. First-level in-memory cache check for current PHP process
        if (isset(static::$tokenCache[$cacheKey])) {
            return static::$tokenCache[$cacheKey];
        }

        // 2. Second-level filesystem cache check (to prevent database-cache infinite recursion)
        $token = Cache::store('file')->remember($cacheKey, 600, function () use ($host, $port, $username, $region) {
            $command = sprintf(
                'aws rds generate-db-auth-token --hostname %s --port %d --username %s --region %s',
                escapeshellarg($host),
                (int) $port,
                escapeshellarg($username),
                escapeshellarg($region)
            );

            // Access keys can be loaded from the environment to configure the AWS CLI
            $env = [];
            if (env('AWS_ACCESS_KEY_ID')) {
                $env['AWS_ACCESS_KEY_ID'] = env('AWS_ACCESS_KEY_ID');
            }
            if (env('AWS_SECRET_ACCESS_KEY')) {
                $env['AWS_SECRET_ACCESS_KEY'] = env('AWS_SECRET_ACCESS_KEY');
            }

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            $process = proc_open($command, $descriptors, $pipes, null, empty($env) ? null : $env);

            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);

                if ($exitCode !== 0) {
                    throw new \RuntimeException(
                        "Failed to generate RDS DB auth token. Exit code: {$exitCode}. Stderr: " . trim($stderr)
                    );
                }

                $tokenVal = trim($stdout);
                if (empty($tokenVal)) {
                    throw new \RuntimeException("Generated RDS DB auth token is empty.");
                }

                return $tokenVal;
            }

            throw new \RuntimeException("Failed to execute aws-cli process for RDS IAM token generation.");
        });

        static::$tokenCache[$cacheKey] = $token;

        return $token;
    }
}

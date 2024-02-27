<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Utopia\CLI\Console;
use Utopia\Database\Adapter;
use Utopia\Database\Adapter\MariaDB;
use Utopia\Database\Validator\Authorization;
use Utopia\DSN\DSN;
use Utopia\Http\Adapter\Swoole\Server;
use Utopia\Http\Http;
use Utopia\Http\Request;
use Utopia\Http\Response;
use Utopia\Http\Route;
use Utopia\Http\Validator\Text;
use Utopia\Logger\Adapter\AppSignal;
use Utopia\Logger\Adapter\LogOwl;
use Utopia\Logger\Adapter\Raygun;
use Utopia\Logger\Adapter\Sentry;
use Utopia\Logger\Log;
use Utopia\Logger\Logger;
use Utopia\Pools\Connection;
use Utopia\Pools\Pool;
use Utopia\Registry\Registry;

const MAX_ARRAY_SIZE = 100000;
const MAX_STRING_SIZE = 20 * 1024 * 1024; // 20 MB
const PAYLOAD_SIZE = 20 * 1024 * 1024; // 20MB

// Unlimited memory limit to handle as many coroutines/requests as possible
ini_set('memory_limit', '-1');

Http::setMode((string) Http::getEnv('UTOPIA_DATA_API_ENV', Http::MODE_TYPE_PRODUCTION));

$registry = new Registry();

$registry->set('logger', function () {
    $providerName = Http::getEnv('UTOPIA_DATA_API_LOGGING_PROVIDER', '');
    $providerConfig = Http::getEnv('UTOPIA_DATA_API_LOGGING_CONFIG', '');
    $logger = null;

    if (!empty($providerName) && !empty($providerConfig) && Logger::hasProvider($providerName)) {
        $adapter = match ($providerName) {
            'sentry' => new Sentry($providerConfig),
            'raygun' => new Raygun($providerConfig),
            'logowl' => new LogOwl($providerConfig),
            'appsignal' => new AppSignal($providerConfig),
            default => throw new Exception('Provider "' . $providerName . '" not supported.')
        };

        $logger = new Logger($adapter);
    }

    return $logger;
});

$registry->set('pool', function () {
    $dsnString = Http::getEnv('UTOPIA_DATA_API_SECRET_CONNECTION', '') ?? '';

    $dsn = new DSN($dsnString);
    $dsnHost = $dsn->getHost();
    $dsnPort = $dsn->getPort();
    $dsnUser = $dsn->getUser();
    $dsnPass = $dsn->getPassword();
    $dsnScheme = $dsn->getScheme();
    $dsnDatabase = $dsn->getPath() ?? '';
    $poolSize = \intval($dsn->getParam('pool_size', '255'));

    $pool = new Pool('adapter-pool', $poolSize, function () use ($dsnScheme, $dsnHost, $dsnPort, $dsnUser, $dsnPass, $dsnDatabase) {
        switch ($dsnScheme) {
            case 'mariadb':
                $pdo = new PDO("mysql:host={$dsnHost};port={$dsnPort};dbname={$dsnDatabase};charset=utf8mb4", $dsnUser, $dsnPass, array(
                    PDO::ATTR_TIMEOUT => 15, // Seconds
                    PDO::ATTR_PERSISTENT => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // PDO will throw a PDOException
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_STRINGIFY_FETCHES => true
                ));
                $adapter = new MariaDB($pdo);
                $adapter->setDatabase($dsnDatabase);

                return $adapter;
        };
    });

    return $pool;
});

$http = new Server("0.0.0.0", Http::getEnv('UTOPIA_DATA_API_PORT', '80'), [
    'open_http2_protocol' => true,
    'http_compression' => true,
    'http_compression_level' => 6,
    'package_max_length' => PAYLOAD_SIZE,
    'buffer_output_size' => PAYLOAD_SIZE,
]);

Http::onStart()
    ->action(function () {
        Console::success("HTTP server started.");
    });

Http::setResource('registry', fn () => $registry);
Http::setResource('logger', fn (Registry $registry) => $registry->get('logger'), ['registry']);
Http::setResource('pool', fn (Registry $registry) => $registry->get('pool'), ['registry']);
Http::setResource('log', fn () => new Log());
Http::setResource('authorization', fn () => new Authorization());

Http::setResource('adapterConnection', function (Pool $pool) {
    $connection = $pool->pop();
    return $connection;
}, ['pool']);

Http::setResource('adapter', function (Request $request, Connection $adapterConnection, Authorization $authorization) {
    $namespace = $request->getHeader('x-utopia-namespace', '');
    $timeoutsString = $request->getHeader('x-utopia-timeouts', '[]');
    $database = $request->getHeader('x-utopia-database', '');
    $roles = $request->getHeader('x-utopia-auth-roles', '');
    $status = $request->getHeader('x-utopia-auth-status', '');
    $statusDefault = $request->getHeader('x-utopia-auth-status-default', '');

    $timeouts = \json_decode($timeoutsString, true);

    /**
     * @var Adapter $resource
     */
    $resource = $adapterConnection->getResource();
    $resource->setAuthorization($authorization);
    $resource->setNamespace($namespace);

    $resource->clearTransformations();

    foreach ($timeouts as $event => $timeout) {
        $resource->setTimeout(\intval($timeout), $event);
    }

    if (!empty($database)) {
        $resource->setDatabase($database);
    } else {
        $resource->setDatabase('');
    }

    $authorization->cleanRoles();
    $authorization->addRole('any');
    foreach (\explode(',', $roles) as $role) {
        $authorization->addRole($role);
    }

    if (!empty($statusDefault) && $statusDefault === 'false') {
        $authorization->setDefaultStatus(false);
    } else {
        $authorization->setDefaultStatus(true);
    }

    if (!empty($status)) {
        if ($status === 'false') {
            $authorization->disable();
        } else {
            $authorization->enable();
        }
    }

    return $resource;
}, ['request', 'adapterConnection', 'authorization']);

Http::init()
    ->groups(['api'])
    ->inject('request')
    ->action(function (Request $request) {
        $secret = Http::getEnv('UTOPIA_DATA_API_SECRET', '');
        $header = $request->getHeader('x-utopia-secret', '');

        if ($header !== $secret) {
            throw new Exception('Incorrect secret in x-utopia-secret header.', 401);
        }
    });

Http::init()
    ->groups(['mock'])
    ->action(function () {
        if (!Http::isDevelopment()) {
            throw new Exception('Mock endpoints are not implemented on production.', 404);
        }
    });

Http::get('/mock/error')
    ->groups(['api', 'mock'])
    ->action(function () {
        throw new Exception('Mock error', 500);
    });

Http::post('/v1/queries')
    ->groups(['api'])
    ->param('query', '', new Text(1024, 1), 'Method name to run with query')
    ->param('params', '', new Text(0, 0), 'Base64 of serialized parameters to pass into a method call', true)
    ->inject('adapter')
    ->inject('request')
    ->inject('response')
    ->action(function (string $query, string $params, Adapter $adapter, Request $request, Response $response) {
        $typedParams = \unserialize(\base64_decode($params));

        /**
         * @var callable $method
         */
        $method = [$adapter, $query];
        $output = call_user_func_array($method, $typedParams);

        $response->json([
            'output' => $output
        ]);
    });

Http::shutdown()
    ->inject('utopia')
    ->inject('context')
    ->action(function (Http $app, string $context) {
        $connection = $app->getResource('adapterConnection', $context);

        if (isset($connection)) {
            $connection->reclaim();
        }
    });

Http::error()
    ->inject('route')
    ->inject('error')
    ->inject('logger')
    ->inject('response')
    ->inject('log')
    ->action(function (?Route $route, Throwable $error, ?Logger $logger, Response $response, Log $log) {
        Console::error('[Error] Type: ' . get_class($error));
        Console::error('[Error] Type: ' . get_class($error));
        Console::error('[Error] Message: ' . $error->getMessage());
        Console::error('[Error] File: ' . $error->getFile());
        Console::error('[Error] Line: ' . $error->getLine());

        if ($logger && ($error->getCode() === 500 || $error->getCode() === 0)) {
            $version = (string) Http::getEnv('UTOPIA_DATA_API_VERSION', '');
            if (empty($version)) {
                $version = 'UNKNOWN';
            }

            $log->setNamespace("database-proxy");
            $log->setServer(\gethostname() !== false ? \gethostname() : null);
            $log->setVersion($version);
            $log->setType(Log::TYPE_ERROR);
            $log->setMessage($error->getMessage());

            if ($route) {
                $log->addTag('method', $route->getMethod());
                $log->addTag('url', $route->getPath());
            }

            $log->addTag('code', \strval($error->getCode()));
            $log->addTag('verboseType', get_class($error));

            $log->addExtra('file', $error->getFile());
            $log->addExtra('line', $error->getLine());
            $log->addExtra('trace', $error->getTraceAsString());
            $log->addExtra('detailedTrace', $error->getTrace());

            $log->setAction('httpError');

            $log->setEnvironment(Http::isProduction() ? Log::ENVIRONMENT_PRODUCTION : Log::ENVIRONMENT_STAGING);

            $responseCode = $logger->addLog($log);
            Console::info('Log pushed with status code: ' . $responseCode);
        }

        switch ($error->getCode()) {
            case 400: // Error allowed publicly
            case 401: // Error allowed publicly
            case 402: // Error allowed publicly
            case 403: // Error allowed publicly
            case 404: // Error allowed publicly
            case 406: // Error allowed publicly
            case 408: // Error allowed publicly
            case 409: // Error allowed publicly
            case 412: // Error allowed publicly
            case 425: // Error allowed publicly
            case 429: // Error allowed publicly
            case 501: // Error allowed publicly
            case 503: // Error allowed publicly
                $code = $error->getCode();
                break;
            default:
                $code = 500; // All other errors get the generic 500 server error status code
        }

        $output = [
            'type' => \get_class($error),
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTrace(),
            'version' => Http::getEnv('UTOPIA_DATA_API_VERSION', 'UNKNOWN')
        ];

        $response
            ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->addHeader('Expires', '0')
            ->addHeader('Pragma', 'no-cache')
            ->setStatusCode(\intval($code));

        $response->json($output);
    });

$app = new Http($http, 'UTC');
$app->start();

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Utopia\CLI\Console;
use Utopia\Database\Adapter\MariaDB;
use Utopia\Database\Validator\Authorization;
use Utopia\DSN\DSN;
use Utopia\Http\Adapter\Swoole\Server;
use Utopia\Http\Http;
use Utopia\Http\Request;
use Utopia\Http\Response;
use Utopia\Registry\Registry;

use function Swoole\Coroutine\run;

require_once __DIR__ . '/endpoints.php';

$registry = new Registry();

$registry->set('adapters', function () {
    $dsns = \explode(',', Http::getEnv('UTOPIA_DATABASE_PROXY_SECRET_CONNECTIONS', '') ?? '');

    $adapters = [];

    foreach ($dsns as $dsnPair) {
        [$dsnName, $dsnString] = explode('=', $dsnPair);
        $dsn = new DSN($dsnString);
        $dsnHost = $dsn->getHost();
        $dsnPort = $dsn->getPort();
        $dsnUser = $dsn->getUser();
        $dsnPass = $dsn->getPassword();
        $dsnScheme = $dsn->getScheme();
        $dsnDatabase = $dsn->getPath() ?? '';

        // TODO: Introduce pools with ?pool_size=64

        switch ($dsnScheme) {
            case 'mariadb':
                $adapters[$dsnName] = function () use ($dsnHost, $dsnPort, $dsnUser, $dsnPass, $dsnDatabase) {
                    $pdo = new PDO("mysql:host={$dsnHost};port={$dsnPort};dbname={$dsnDatabase};charset=utf8mb4", $dsnUser, $dsnPass, array(
                        PDO::ATTR_TIMEOUT => 15, // Seconds
                        PDO::ATTR_PERSISTENT => false,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        // PDO::ATTR_ERRMODE => Http::isDevelopment() ? PDO::ERRMODE_WARNING : PDO::ERRMODE_SILENT, // If in production mode, warnings are not displayed
                        PDO::ATTR_EMULATE_PREPARES => true,
                        PDO::ATTR_STRINGIFY_FETCHES => true
                    ));
                    $adapter = new MariaDB($pdo);
                    $adapter->setDefaultDatabase($dsnDatabase);
                    return $adapter;
                };
                break;
        };
    }

    return $adapters;
});

$http = new Server("0.0.0.0", Http::getEnv('UTOPIA_DATABASE_PROXY_PORT', '80'));

$payloadSize = 20 * (1024 * 1024); // 20MB

$http
    ->setConfig([
        'open_http2_protocol' => true,
        'http_compression' => true,
        'http_compression_level' => 6,
        'package_max_length' => $payloadSize,
        'buffer_output_size' => $payloadSize,
    ]);

Http::onStart()
    ->action(function () {
        Console::success("HTTP server started.");
    });

Http::setResource('registry', fn () => $registry);

Http::setResource('adapters', fn (Registry $registry) => $registry->get('adapters'), ['registry']);

Http::setResource('adapter', function (Request $request, array $adapters) {
    $database = $request->getHeader('x-utopia-database', '');
    $namespace = $request->getHeader('x-utopia-namespace', '');
    $timeout = $request->getHeader('x-utopia-timeout', '');
    $defaultDatabase = $request->getHeader('x-utopia-default-database', '');
    $roles = $request->getHeader('x-utopia-auth-roles', '');
    $status = $request->getHeader('x-utopia-auth-status', '');
    $statusDefault = $request->getHeader('x-utopia-auth-status-default', '');

    if (empty($database)) {
        throw new Exception('Incorrect database in x-utopia-database header.', 400);
    }

    $adapter = $adapters[$database] ?? null;

    if (empty($adapter)) {
        throw new Exception('Incorrect database in x-utopia-database header.', 400);
    }

    $resource = $adapter();
    $resource->setNamespace($namespace);

    if (!empty($timeout)) {
        $resource->setTimeout($timeout);
    } else {
        $resource->clearTimeout();
    }

    if (!empty($defaultDatabase)) {
        $resource->setDefaultDatabase($defaultDatabase);
    } else {
        $resource->setDefaultDatabase('');
    }

    Authorization::cleanRoles();
    Authorization::setRole('any');
    foreach (\explode(',', $roles) as $role) {
        Authorization::setRole($role);
    }

    if (!empty($statusDefault)) {
        if ($statusDefault === 'true') {
            Authorization::setDefaultStatus(true);
        } else {
            Authorization::setDefaultStatus(false);
        }
    }

    if (!empty($status)) {
        if ($status === 'true') {
            Authorization::enable();
        } else {
            Authorization::disable();
        }
    }

    return $resource;
}, ['request', 'adapters']);

Http::init()
    ->groups(['api'])
    ->inject('request')
    ->action(function (Request $request) {
        $secret = Http::getEnv('UTOPIA_DATABASE_PROXY_SECRET', '');
        $header = $request->getHeader('x-utopia-secret', '');

        if ($header !== $secret) {
            throw new Exception('Incorrect secret in x-utopia-secret header.', 401);
        }
    });

Http::error()
    ->inject('error')
    ->inject('response')
    ->action(function (Throwable $error, Response $response) {
        // TODO: Log to Sentry

        switch ($error->getCode()) {
            case 400: // Error allowed publicly
            case 401: // Error allowed publicly
            case 402: // Error allowed publicly
            case 403: // Error allowed publicly
            case 404: // Error allowed publicly
            case 406: // Error allowed publicly
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
            'version' => Http::getEnv('UTOPIA_DATABASE_PROXY_VERSION', 'UNKNOWN')
        ];

        $response
            ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->addHeader('Expires', '0')
            ->addHeader('Pragma', 'no-cache')
            ->setStatusCode(\intval($code));

        $response->json($output);
    });

run(function () use ($http) {
    $app = new Http($http, 'UTC');
    $app->start();
});

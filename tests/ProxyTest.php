<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Helpers\Role;
use Utopia\Fetch\Client;
use Utopia\Fetch\Response;

final class ProxyTest extends TestCase
{
    protected string $endpoint = 'http://database-proxy/v1';
    protected string $secret = 'proxy-secret-key';
    protected string $namespace = 'my-namespace';
    protected string $database = 'appwrite';
    protected bool $defaultAuthStatus = true;
    protected int $timeout = 10000; // Milliseconds

    protected string $testUniqueId;

    protected function setUp(): void
    {
        $this->testUniqueId = \uniqid();
        $this->namespace .= '-' . $this->testUniqueId;
        $this->database .=  '-' . $this->testUniqueId;

        $this->call('POST', '/queries', [ 'query' => 'create', 'params' => [ $this->database ] ]);
    }

    /**
     * @param array<string> $roles
     */
    private function call(string $method, string $endpoint, mixed $body = [], array $roles = [], bool $skipAuth = false): Response
    {
        if(isset($body['params'])) {
            $body['params'] = \base64_encode(\serialize($body['params']));
        }

        $timeouts = \json_encode(['*' => $this->timeout]);
        if($timeouts == false) {
            $timeouts = '';
        }

        return Client::fetch($this->endpoint . $endpoint, [
            'x-utopia-secret' => $this->secret,
            'x-utopia-namespace' => $this->namespace,
            'x-utopia-database' => $this->database,
            'x-utopia-auth-roles' => \implode(',', $roles),
            'x-utopia-auth-status' => $skipAuth ? 'false' : 'true',
            'x-utopia-auth-status-default' => $this->defaultAuthStatus ? 'true' : 'false',
            'x-utopia-timeouts' => $timeouts,
            'content-type' => 'application/json'
        ], $method, $body);
    }

    public function testSecret(): void
    {
        $correctSecret = $this->secret;
        $this->secret = 'wrong-secret';
        $response = $this->call('POST', '/queries', [ 'query' => 'ping' ]);
        self::assertEquals(401, $response->getStatusCode());
        $this->secret = $correctSecret;
    }

    public function testTimeout(): void
    {
        $correctTimeout = $this->timeout;

        $this->timeout = 600000;
        $response = $this->call('POST', '/queries', [ 'query' => 'ping' ]);
        self::assertEquals(200, $response->getStatusCode());

        $this->timeout = -1;
        $response = $this->call('POST', '/queries', [ 'query' => 'ping' ]);
        self::assertEquals(500, $response->getStatusCode());

        $this->timeout = $correctTimeout;
    }

    public function testMock(): void
    {
        $correctEndpoint = $this->endpoint;
        $this->endpoint = 'http://database-proxy/mock';
        $response = $this->call('GET', '/error');
        self::assertEquals(500, $response->getStatusCode());
        $this->endpoint = $correctEndpoint;
    }

    public function testPing(): void
    {
        $response = $this->call('POST', '/queries', [ 'query' => 'ping' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    public function testDatabase(): void
    {
        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database . '-wrong' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'create', 'params' => [ $this->database . '-wrong' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database . '-wrong' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $correctDatabase = $this->database;
        $this->database .= '-wrong';
        $response = $this->call('POST', '/queries', [ 'query' => 'createCollection', 'params' => [ 'default-db-test' ] ]);
        $this->database = $correctDatabase;
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database . '-wrong', 'default-db-test' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database, 'default-db-test' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);
    }

    public function testNamespace(): void
    {
        $correctNamespace = $this->namespace;
        $this->namespace .= '-wrong';

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database, 'cars' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'createCollection', 'params' => [ 'cars' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database, 'cars' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $this->namespace = $correctNamespace;

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database, 'cars' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);
    }

    public function testAuth(): void
    {
        $response = $this->call('POST', '/queries', [ 'query' => 'createCollection', 'params' => [ 'passwords', [
            new Document([
                '$id' => 'password',
                'type' => Database::VAR_STRING,
                'size' => 512,
                'required' => true
            ])
        ] ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'exists', 'params' => [ $this->database, 'passwords' ] ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $docAny = ID::unique();
        $response = $this->call('POST', '/queries', [ 'query' => 'createDocument', 'params' => [
            'passwords', new Document([
                '$id' => $docAny,
                '$permissions' => [
                    Permission::read(Role::any())
                ],
                'password' => 'any-password'
            ])
        ] ]);
        self::assertEquals(200, $response->getStatusCode());

        $docsGuests = ID::unique();
        $response = $this->call('POST', '/queries', [ 'query' => 'createDocument', 'params' => [
            'passwords', new Document([
                '$id' => $docsGuests,
                '$permissions' => [
                    Permission::read(Role::guests())
                ],
                'password' => 'guests-password'
            ])
        ]]);
        self::assertEquals(200, $response->getStatusCode());

        $docUsers = ID::unique();
        $response = $this->call('POST', '/queries', [ 'query' => 'createDocument', 'params' => [
            'passwords', new Document([
                '$id' => $docUsers,
                '$permissions' => [
                    Permission::read(Role::users())
                ],
                'password' => 'users-password'
            ])
        ]]);
        self::assertEquals(200, $response->getStatusCode());

        $docTeam = ID::unique();
        $response = $this->call('POST', '/queries', [ 'query' => 'createDocument', 'params' => [
            'passwords', new Document([
                '$id' => $docTeam,
                '$permissions' => [
                    Permission::read(Role::team('admin'))
                ],
                'password' => 'team-password'
            ])
        ]]);
        self::assertEquals(200, $response->getStatusCode());

        $response = $this->call('POST', '/queries', [ 'query' => 'find', 'params' => ['passwords'] ], [ ], true);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(4, $body['output']);


        $response = $this->call('POST', '/queries', [ 'query' => 'find', 'params' => ['passwords'] ], [], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(1, $body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'find', 'params' => ['passwords'] ], [
            'users'
        ], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(2, $body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'find', 'params' => ['passwords'] ], [
            'guests'
        ], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(2, $body['output']);

        $response = $this->call('POST', '/queries', [ 'query' => 'find', 'params' => ['passwords'] ], [
            'users',
            'guests',
            'team:admin'
        ], false);

        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(4, $body['output']);
    }

    // TODO: Tests for x-utopia-share-tables
    // TODO: Tests for x-utopia-tenant
}

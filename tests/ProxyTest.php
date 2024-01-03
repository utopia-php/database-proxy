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
    protected string $endpoint = 'http://tests/v1';
    protected string $secret = 'proxy-secret-key';
    protected string $namespace = 'my-namespace';
    protected string $defaultDatabase = 'appwrite';
    protected bool $defaultAuthStatus = true;
    protected int $timeout = 10000; // Milliseconds

    protected string $testUniqueId;

    protected function setUp(): void
    {
        $this->testUniqueId = \uniqid();
        $this->namespace .= '-' . $this->testUniqueId;
        $this->defaultDatabase .=  '-' . $this->testUniqueId;

        $this->call('POST', '/databases', [ 'database' => $this->defaultDatabase ]);
    }

    /**
     * @param array<string> $roles
     */
    private function call(string $method, string $endpoint, mixed $body = [], array $roles = [], bool $skipAuth = false): Response
    {
        return Client::fetch($this->endpoint . $endpoint, [
            'x-utopia-secret' => $this->secret,
            'x-utopia-namespace' => $this->namespace,
            'x-utopia-default-database' => $this->defaultDatabase,
            'x-utopia-auth-roles' => \implode(',', $roles),
            'x-utopia-auth-status' => $skipAuth ? 'false' : 'true',
            'x-utopia-auth-status-default' => $this->defaultAuthStatus ? 'true' : 'false',
            'x-utopia-timeout' => \strval($this->timeout),
            'content-type' => 'application/json'
        ], $method, $body);
    }

    public function testSecret(): void
    {
        $correctSecret = $this->secret;
        $this->secret = 'wrong-secret';
        $response = $this->call('GET', '/ping');
        self::assertEquals(401, $response->getStatusCode());
        $this->secret = $correctSecret;
    }

    public function testDefaultDatabase(): void
    {
        $response = $this->call('GET', '/databases/' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/databases/' . $this->defaultDatabase . '-wrong');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/databases', [ 'database' => $this->defaultDatabase . '-wrong' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/databases/' . $this->defaultDatabase . '-wrong');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $correctDefaultDatabase = $this->defaultDatabase;
        $this->defaultDatabase .= '-wrong';
        $response = $this->call('POST', '/collections', [
            'collection' => 'default-db-test',
            'attributes' => [],
            'indexes' => []
        ]);
        $this->defaultDatabase = $correctDefaultDatabase;
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/collections/default-db-test?database=' . $this->defaultDatabase . '-wrong');

        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);


        $response = $this->call('GET', '/collections/default-db-test?database=' . $this->defaultDatabase);

        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);
    }

    public function testNamespace(): void
    {
        $correctNamespace = $this->namespace;
        $this->namespace .= '-wrong';

        $response = $this->call('GET', '/collections/cars?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/collections', [
            'collection' => 'cars',
            'attributes' => [],
            'indexes' => []
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/collections/cars?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $this->namespace = $correctNamespace;

        $response = $this->call('GET', '/collections/cars?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);
    }

    public function testTimeout(): void
    {
        $correctTimeout = $this->timeout;

        $this->timeout = 600000;
        $response = $this->call('GET', '/ping');
        self::assertEquals(200, $response->getStatusCode());

        $this->timeout = -1;
        $response = $this->call('GET', '/ping');
        self::assertEquals(500, $response->getStatusCode());

        $this->timeout = $correctTimeout;
    }

    public function testAuth(): void
    {
        $response = $this->call('POST', '/collections', [
            'collection' => 'passwords',
            'attributes' => [
                new Document([
                    '$id' => 'password',
                    'type' => Database::VAR_STRING,
                    'size' => 512,
                    'required' => true
                ])
            ],
            'indexes' => []
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/collections/passwords?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $docAny = ID::unique();
        $response = $this->call('POST', '/collections/passwords/documents', [
            'document' => new Document([
                '$id' => $docAny,
                '$permissions' => [
                    Permission::read(Role::any())
                ],
                'password' => 'any-password'
            ])
        ]);
        self::assertEquals(200, $response->getStatusCode());

        $docsGuests = ID::unique();
        $response = $this->call('POST', '/collections/passwords/documents', [
            'document' => new Document([
                '$id' => $docsGuests,
                '$permissions' => [
                    Permission::read(Role::guests())
                ],
                'password' => 'guests-password'
            ])
        ]);
        self::assertEquals(200, $response->getStatusCode());

        $docUsers = ID::unique();
        $response = $this->call('POST', '/collections/passwords/documents', [
            'document' => new Document([
                '$id' => $docUsers,
                '$permissions' => [
                    Permission::read(Role::users())
                ],
                'password' => 'users-password'
            ])
        ]);
        self::assertEquals(200, $response->getStatusCode());

        $docTeam = ID::unique();
        $response = $this->call('POST', '/collections/passwords/documents', [
            'document' => new Document([
                '$id' => $docTeam,
                '$permissions' => [
                    Permission::read(Role::team('admin'))
                ],
                'password' => 'team-password'
            ])
        ]);
        self::assertEquals(200, $response->getStatusCode());

        $response = $this->call('GET', '/collections/passwords/documents', [], [], true);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(4, $body['output']);


        $response = $this->call('GET', '/collections/passwords/documents', [], [], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(1, $body['output']);

        $response = $this->call('GET', '/collections/passwords/documents', [], [
            'users'
        ], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(2, $body['output']);

        $response = $this->call('GET', '/collections/passwords/documents', [], [
            'guests'
        ], false);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(2, $body['output']);

        $response = $this->call('GET', '/collections/passwords/documents', [], [
            'users',
            'guests',
            'team:admin'
        ], false);

        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertCount(4, $body['output']);
    }

    public function testMock(): void
    {
        $correctEndpoint = $this->endpoint;
        $this->endpoint = 'http://tests/mock';
        $response = $this->call('GET', '/error');
        self::assertEquals(500, $response->getStatusCode());
        $this->endpoint = $correctEndpoint;
    }

    public function testPing(): void
    {
        $response = $this->call('GET', '/ping');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    public function testExists(): void
    {
        $response = $this->call('GET', '/collections/books');
        self::assertEquals(400, $response->getStatusCode());

        $response = $this->call('GET', '/databases/wrong-database');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);


        $response = $this->call('GET', '/databases/' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/collections/books?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/collections', [
            'collection' => 'books',
            'attributes' => [],
            'indexes' => []
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('GET', '/collections/books?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    /**
     * TODO: We do a lot of E2E testing in utopia/database adapter.
     * But eventuelly, lets add tests here for all endpoints:
     *
     * Http::post('/v1/databases')
     * Http::delete('/v1/databases/:database')
     * Http::post('/v1/collections')
     * Http::delete('/v1/collections/:collection')
     * Http::post('/v1/collections/:collection/attributes')
     * Http::put('/v1/collections/:collection/attributes/:attribute')
     * Http::delete('/v1/collections/:collection/attributes/:attribute')
     * Http::patch('/v1/collections/:collection/attributes/:attribute/name')
     * Http::post('/v1/collections/:collection/indexes')
     * Http::patch('/v1/collections/:collection/indexes/:index/name')
     * Http::delete('/v1/collections/:collection/indexes/:index')
     * Http::get('/v1/collections/:collection/size')
     * Http::get('/v1/collections/:collection/widths/attributes')
     * Http::post('/v1/collections/:collection/documents')
     * Http::put('/v1/collections/:collection/documents')
     * Http::delete('/v1/collections/:collection/documents/:document')
     * Http::get('/v1/collections/:collection/documents/:document')
     * Http::get('/v1/collections/:collection/documents')
     * Http::get('/v1/collections/:collection/documents-sum')
     * Http::get('/v1/collections/:collection/documents-count')
     * Http::patch('/v1/collections/:collection/documents/:document/increase')
     * Http::post('/v1/collections/:collection/relationships')
     * Http::put('/v1/collections/:collection/relationships/:relatedCollection')
     * Http::delete('/v1/collections/:collection/relationships/:relatedCollection')
     */
}

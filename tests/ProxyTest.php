<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Fetch\Client;
use Utopia\Fetch\Response;

final class ProxyTest extends TestCase
{
    protected string $endpoint = 'http://tests/v1';
    protected string $secret = 'proxy-secret-key';
    protected string $database = 'default';
    protected string $namespace = 'my-namespace';
    protected string $defaultDatabase = 'appwrite';
    protected bool $defaultAuthStatus = true;
    protected int $timeout = 10; // Seconds

    protected function setUp(): void
    {
        $this->namespace .= '-' . \uniqid();
    }

    private function call(string $method, string $endpoint, mixed $body = [], array $roles = [], bool $skipAuth = false): Response
    {
        return Client::fetch($this->endpoint . $endpoint, [
            'x-utopia-secret' => $this->secret,
            'x-utopia-database' => $this->database,
            'x-utopia-namespace' => $this->namespace,
            'x-utopia-default-database' => $this->defaultDatabase,
            'x-utopia-auth-roles' => \implode(',', $roles),
            'x-utopia-auth-status' => $skipAuth ? 'false' : 'true',
            'x-utopia-auth-status-default' => $this->defaultAuthStatus ? 'true' : false,
            'x-utopia-timeout' => $this->timeout,
            'content-type' => 'application/json'
        ], $method, $body);
    }

    public function testSecret(): void
    {
        $correctSecret = $this->secret;
        $this->secret = 'wrong-secret';
        $response = $this->call('GET', 'ping');
        self::assertEquals(401, $response->getStatusCode());
        $this->secret = $correctSecret;
    }

    public function testDatabase(): void
    {
        $correctDatabase = $this->database;
        $this->database = 'wrong-database';
        $response = $this->call('ping');
        self::assertEquals(400, $response->getStatusCode());
        $this->database = $correctDatabase;
    }

    public function testDefaultDatabase(): void
    {
        $response = $this->call('exists', [
            'database' => 'wrong-default-database',
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $correctDefaultDatabase = $this->defaultDatabase;
        $this->defaultDatabase = 'wrong-default-database';
        $response = $this->call('createCollection', [
            'name' => 'defaultDbTest',
            'attributes' => [],
            'indexes' => []
        ]);

        $response = $this->call('exists', [
            'database' => 'wrong-default-database',
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('exists', [
            'database' => 'wrong-default-database',
            'collection' => 'defaultDbTest'
        ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $this->defaultDatabase = $correctDefaultDatabase;
    }

    public function testNamespace(): void
    {
        $correctNamespace = $this->namespace;
        $this->namespace = $this->namespace . '-wrong';

        $response = $this->call('exists', [ 'database' => $this->defaultDatabase, 'collection' => 'cars' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('createCollection', [
            'name' => 'cars',
            'attributes' => [],
            'indexes' => []
        ]);
        
        $response = $this->call('exists', [ 'database' => $this->defaultDatabase, 'collection' => 'cars' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $this->namespace = $correctNamespace;

        $response = $this->call('exists', [ 'database' => $this->defaultDatabase, 'collection' => 'cars' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);
    }

    public function testMock(): void
    {
        $correctEndpoint = $this->endpoint;
        $this->endpoint = 'http://tests/mock/';
        $response = $this->call('error');
        self::assertEquals(500, $response->getStatusCode());
        $this->endpoint = $correctEndpoint;
    }
    
    public function testPing(): void
    {
        $response = $this->call('ping');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    public function testExists(): void
    {
        $response = $this->call('exists');
        self::assertEquals(400, $response->getStatusCode());

        $response = $this->call('exists', [ 'collection' => 'books' ]);
        self::assertEquals(400, $response->getStatusCode());

        $response = $this->call('exists', [ 'database' => 'wrong-database' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('exists', [ 'database' => $this->defaultDatabase ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);

        $response = $this->call('exists', [ 'database' => $this->defaultDatabase, 'collection' => 'books' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('createCollection', [
            'name' => 'books',
            'attributes' => [],
            'indexes' => []
        ]);

        $response = $this->call('exists', [ 'database' => $this->defaultDatabase, 'collection' => 'books' ]);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    // TODO: Test for every endpoint
    // TODO: Timeout test
    // TODO: Roles test
    // TODO: Auth Status test
}
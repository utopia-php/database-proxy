<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Fetch\Client;
use Utopia\Fetch\Response;

final class ProxyTest extends TestCase
{
    protected string $endpoint = 'http://tests/v1';
    protected string $secret = 'proxy-secret-key';
    protected string $namespace = 'my-namespace';
    protected string $defaultDatabase = 'appwrite';
    protected bool $defaultAuthStatus = true;
    protected int $timeout = 10; // Seconds

    protected function setUp(): void
    {
        $this->namespace .= '-' . \uniqid();
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
        $response = $this->call('GET', '/databases/wrong-default-database');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $correctDefaultDatabase = $this->defaultDatabase;
        $this->defaultDatabase = 'wrong-default-database';
        $response = $this->call('POST', '/collections', [
            'collection' => 'default-db-test',
            'attributes' => [],
            'indexes' => []
        ]);

        $response = $this->call('GET', '/databases/wrong-default-database');
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('GET', '/collections/default-db-test?database=wrong-default-database');

        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $this->defaultDatabase = $correctDefaultDatabase;
    }

    public function testNamespace(): void
    {
        $correctNamespace = $this->namespace;
        $this->namespace = $this->namespace . '-wrong';

        $response = $this->call('GET', '/collections/cars?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertFalse($body['output']);

        $response = $this->call('POST', '/collections', [
            'collection' => 'cars',
            'attributes' => [],
            'indexes' => []
        ]);

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

    // TODO: Timeout test
    // TODO: Roles test
    // TODO: Auth Status test

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

        $response = $this->call('GET', '/collections/books?database=' . $this->defaultDatabase);
        self::assertEquals(200, $response->getStatusCode());
        $body = \json_decode($response->getBody(), true);
        self::assertTrue($body['output']);
    }

    /**
     * TODO: Add tests for all endpoints:
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
     * Http::get('/v1/collections/:collection/counts/attributes')
     * Http::get('/v1/collections/:collection/counts/indexes')
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

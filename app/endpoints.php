<?php

use Utopia\Database\Adapter;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Database\Query;
use Utopia\Http\Http;
use Utopia\Http\Response;
use Utopia\Http\Validator\ArrayList;
use Utopia\Http\Validator\Assoc;
use Utopia\Http\Validator\Boolean;
use Utopia\Http\Validator\FloatValidator;
use Utopia\Http\Validator\Integer;
use Utopia\Http\Validator\Text;

Http::get('/mock/error')
    ->groups(['api', 'mock'])
    ->action(function () {
        throw new Exception('Mock error', 500);
    });

Http::get('/v1/ping')
    ->groups(['api'])
    ->inject('adapter')
    ->inject('response')
    ->action(function (Adapter $adapter, Response $response) {
        $output = $adapter->ping();

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/databases/:database')
    ->groups(['api'])
    ->param('database', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $database, Adapter $adapter, Response $response) {
        $output = $adapter->exists($database, null);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection')
    ->groups(['api'])
    ->param('database', '', new Text(MAX_STRING_SIZE, 0))
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $database, string $collection, Adapter $adapter, Response $response) {
        $output = $adapter->exists($database, $collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/databases')
    ->groups(['api'])
    ->param('database', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $database, Adapter $adapter, Response $response) {
        $output = $adapter->create($database);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/databases/:database')
    ->groups(['api'])
    ->param('database', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $database, Adapter $adapter, Response $response) {
        $output = $adapter->delete($database);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/collections')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attributes', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->param('indexes', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, array $attributes, array $indexes, Adapter $adapter, Response $response) {
        foreach ($attributes as &$attribute) {
            $attribute = new Document($attribute);
        }

        foreach ($indexes as &$index) {
            $index = new Document($index);
        }

        $output = $adapter->createCollection($collection, $attributes, $indexes);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/collections/:collection')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, Adapter $adapter, Response $response) {
        $output = $adapter->deleteCollection($collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/collections/:collection/attributes')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('size', '', new Integer())
    ->param('signed', true, new Boolean(), '', true)
    ->param('array', false, new Boolean(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $attribute, string $type, int $size, bool $signed, bool $array, Adapter $adapter, Response $response) {
        $output = $adapter->createAttribute($collection, $attribute, $type, $size, $signed, $array);

        $response->json([
            'output' => $output
        ]);
    });

Http::put('/v1/collections/:collection/attributes/:attribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('size', '', new Integer())
    ->param('signed', true, new Boolean(), '', true)
    ->param('array', false, new Boolean(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $attribute, string $type, int $size, bool $signed, bool $array, Adapter $adapter, Response $response) {
        $output = $adapter->updateAttribute($collection, $attribute, $type, $size, $signed, $array);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/collections/:collection/attributes/:attribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $attribute, Adapter $adapter, Response $response) {
        $output = $adapter->deleteAttribute($collection, $attribute);

        $response->json([
            'output' => $output
        ]);
    });

Http::patch('/v1/collections/:collection/attributes/:attribute/name')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('new', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $attribute, string $new, Adapter $adapter, Response $response) {
        $output = $adapter->renameAttribute($collection, $attribute, $new);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/collections/:collection/indexes')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('index', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attributes', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE))
    ->param('lengths', [], new ArrayList(new Integer(), MAX_ARRAY_SIZE))
    ->param('orders', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $index, string $type, array $attributes, array $lengths, array $orders, Adapter $adapter, Response $response) {
        $output = $adapter->createIndex($collection, $index, $type, $attributes, $lengths, $orders);

        $response->json([
            'output' => $output
        ]);
    });

Http::patch('/v1/collections/:collection/indexes/:index/name')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('index', '', new Text(MAX_STRING_SIZE, 0))
    ->param('new', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $index, string $new, Adapter $adapter, Response $response) {
        $output = $adapter->renameIndex($collection, $index, $new);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/collections/:collection/indexes/:index')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('index', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $index, Adapter $adapter, Response $response) {
        $output = $adapter->deleteIndex($collection, $index);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/size')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, Adapter $adapter, Response $response) {
        $output = $adapter->getSizeOfCollection($collection);

        $response->json([
            'output' => $output
        ]);
    });


Http::get('/v1/collections/:collection/counts/attributes')
    ->groups(['api'])
    ->param('collection', '', new Assoc(MAX_STRING_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (array $collection, Adapter $adapter, Response $response) {
        $collection = new Document($collection);

        $output = $adapter->getCountOfAttributes($collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/counts/indexes')
    ->groups(['api'])
    ->param('collection', '', new Assoc(MAX_STRING_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (array $collection, Adapter $adapter, Response $response) {
        $collection = new Document($collection);

        $output = $adapter->getCountOfIndexes($collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/widths/attributes')
    ->groups(['api'])
    ->param('collection', '', new Assoc(MAX_STRING_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (array $collection, Adapter $adapter, Response $response) {
        $collection = new Document($collection);

        $output = $adapter->getAttributeWidth($collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/collections/:collection/documents')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('document', '', new Assoc(MAX_STRING_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, array $document, Adapter $adapter, Response $response) {
        $document = new Document($document);

        $output = $adapter->createDocument($collection, $document);

        $response->json([
            'output' => $output
        ]);
    });

Http::put('/v1/collections/:collection/documents')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('document', '', new Assoc(MAX_STRING_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, array $document, Adapter $adapter, Response $response) {
        $document = new Document($document);

        $output = $adapter->updateDocument($collection, $document);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/collections/:collection/documents/:document')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('document', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $document, Adapter $adapter, Response $response) {
        $output = $adapter->deleteDocument($collection, $document);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/documents/:document')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('document', '', new Text(MAX_STRING_SIZE, 0))
    ->param('queries', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $document, array $queries, Adapter $adapter, Response $response) {
        foreach ($queries as &$query) {
            $query = new Query($query['method'], $query['attribute'] ?? '', $query['values'] ?? []);
        }

        $output = $adapter->getDocument($collection, $document, $queries);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/documents')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('queries', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->param('limit', 25, new Integer(), '', true)
    ->param('offset', null, new Integer(), '', true)
    ->param('orderAttributes', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE), '', true)
    ->param('orderTypes', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE), '', true)
    ->param('cursor', [], new Assoc(MAX_STRING_SIZE), '', true)
    ->param('cursorDirection', Database::CURSOR_AFTER, new Text(MAX_STRING_SIZE, 0), '', true)
    ->param('timeout', null, new Integer(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, array $queries, ?int $limit, ?int $offset, array $orderAttributes, array $orderTypes, array $cursor, string $cursorDirection, ?int $timeout, Adapter $adapter, Response $response) {
        foreach ($queries as &$query) {
            $query = new Query($query['method'], $query['attribute'] ?? '', $query['values'] ?? []);
        }

        $output = $adapter->find($collection, $queries, $limit, $offset, $orderAttributes, $orderTypes, $cursor, $cursorDirection, $timeout);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/documents-sum')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('queries', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->param('max', null, new Integer(), '', true)
    ->param('timeout', null, new Integer(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $attribute, array $queries, ?int $max, ?int $timeout, Adapter $adapter, Response $response) {
        foreach ($queries as &$query) {
            $query = new Query($query['method'], $query['attribute'] ?? '', $query['values'] ?? []);
        }

        $output = $adapter->sum($collection, $attribute, $queries, $max, $timeout);

        $response->json([
            'output' => $output
        ]);
    });

Http::get('/v1/collections/:collection/documents-count')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('queries', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->param('max', null, new Integer(), '', true)
    ->param('timeout', null, new Integer(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, array $queries, ?int $max, ?int $timeout, Adapter $adapter, Response $response) {
        foreach ($queries as &$query) {
            $query = new Query($query['method'], $query['attribute'] ?? '', $query['values'] ?? []);
        }

        $output = $adapter->count($collection, $queries, $max, $timeout);

        $response->json([
            'output' => $output
        ]);
    });

Http::patch('/v1/collections/:collection/documents/:document/increase')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('document', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('value', null, new FloatValidator(), '', true)
    ->param('min', null, new FloatValidator(), '', true)
    ->param('max', null, new FloatValidator(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $document, string $attribute, float $value, ?float $min, ?float $max, Adapter $adapter, Response $response) {
        $output = $adapter->increaseDocumentAttribute($collection, $document, $attribute, $value, $min, $max);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/collections/:collection/relationships')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('relatedCollection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('twoWay', false, new Boolean(), '', true)
    ->param('id', '', new Text(MAX_STRING_SIZE, 0), '', true)
    ->param('twoWayKey', '', new Text(MAX_STRING_SIZE, 0), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $relatedCollection, string $type, bool $twoWay, string $id, string $twoWayKey, Adapter $adapter, Response $response) {
        $output = $adapter->createRelationship($collection, $relatedCollection, $type, $twoWay, $id, $twoWayKey);

        $response->json([
            'output' => $output
        ]);
    });

Http::put('/v1/collections/:collection/relationships/:relatedCollection')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('relatedCollection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('twoWay', false, new Boolean())
    ->param('key', '', new Text(MAX_STRING_SIZE, 0))
    ->param('twoWayKey', '', new Text(MAX_STRING_SIZE, 0))
    ->param('newKey', null, new Text(MAX_STRING_SIZE, 0), '', true)
    ->param('newTwoWayKey', null, new Text(MAX_STRING_SIZE, 0), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $relatedCollection, string $type, bool $twoWay, string $key, string $twoWayKey, string $newKey, string $newTwoWayKey, Adapter $adapter, Response $response) {
        $output = $adapter->updateRelationship($collection, $relatedCollection, $type, $twoWay, $key, $twoWayKey, $newKey, $newTwoWayKey);

        $response->json([
            'output' => $output
        ]);
    });

Http::delete('/v1/collections/:collection/relationships/:relatedCollection')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('relatedCollection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('twoWay', false, new Boolean())
    ->param('key', '', new Text(MAX_STRING_SIZE, 0))
    ->param('twoWayKey', '', new Text(MAX_STRING_SIZE, 0))
    ->param('side', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $relatedCollection, string $type, bool $twoWay, string $key, string $twoWayKey, string $side, Adapter $adapter, Response $response) {
        $output = $adapter->deleteRelationship($collection, $relatedCollection, $type, $twoWay, $key, $twoWayKey, $side);

        $response->json([
            'output' => $output
        ]);
    });

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

Http::post('/mock/error')
    ->groups(['api'])
    ->action(function () {
        throw new Exception('Mock error', 500);
    });

Http::post('/v1/queries/ping')
    ->groups(['api'])
    ->inject('adapter')
    ->inject('response')
    ->action(function (Adapter $adapter, Response $response) {
        $output = $adapter->ping();

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/exists')
    ->groups(['api'])
    ->param('database', '', new Text(MAX_STRING_SIZE, 0))
    ->param('collection', null, new Text(MAX_STRING_SIZE, 0), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $database, ?string $collection, Adapter $adapter, Response $response) {
        $output = $adapter->exists($database, $collection);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/create')
    ->groups(['api'])
    ->param('name', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $name, Adapter $adapter, Response $response) {
        $output = $adapter->create($name);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/delete')
    ->groups(['api'])
    ->param('name', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $name, Adapter $adapter, Response $response) {
        $output = $adapter->delete($name);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/createCollection')
    ->groups(['api'])
    ->param('name', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attributes', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->param('indexes', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $name, array $attributes, array $indexes, Adapter $adapter, Response $response) {
        foreach ($attributes as &$attribute) {
            $attribute = new Document($attribute);
        }

        foreach ($indexes as &$index) {
            $index = new Document($index);
        }

        $output = $adapter->createCollection($name, $attributes, $indexes);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/deleteCollection')
    ->groups(['api'])
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $id, Adapter $adapter, Response $response) {
        $output = $adapter->deleteCollection($id);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/createAttribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('size', '', new Integer())
    ->param('signed', true, new Boolean(), '', true)
    ->param('array', false, new Boolean(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, string $type, int $size, bool $signed, bool $array, Adapter $adapter, Response $response) {
        $output = $adapter->createAttribute($collection, $id, $type, $size, $signed, $array);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/updateAttribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('size', '', new Integer())
    ->param('signed', true, new Boolean(), '', true)
    ->param('array', false, new Boolean(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, string $type, int $size, bool $signed, bool $array, Adapter $adapter, Response $response) {
        $output = $adapter->updateAttribute($collection, $id, $type, $size, $signed, $array);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/deleteAttribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, Adapter $adapter, Response $response) {
        $output = $adapter->deleteAttribute($collection, $id);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/renameAttribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('old', '', new Text(MAX_STRING_SIZE, 0))
    ->param('new', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $old, string $new, Adapter $adapter, Response $response) {
        $output = $adapter->renameAttribute($collection, $old, $new);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/createIndex')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->param('type', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attributes', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE))
    ->param('lengths', [], new ArrayList(new Integer(), MAX_ARRAY_SIZE))
    ->param('orders', [], new ArrayList(new Text(MAX_STRING_SIZE, 0), MAX_ARRAY_SIZE))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, string $type, array $attributes, array $lengths, array $orders, Adapter $adapter, Response $response) {
        $output = $adapter->createIndex($collection, $id, $type, $attributes, $lengths, $orders);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/renameIndex')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('old', '', new Text(MAX_STRING_SIZE, 0))
    ->param('new', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $old, string $new, Adapter $adapter, Response $response) {
        $output = $adapter->renameIndex($collection, $old, $new);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/deleteIndex')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, Adapter $adapter, Response $response) {
        $output = $adapter->deleteIndex($collection, $id);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/getSizeOfCollection')
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


Http::post('/v1/queries/getCountOfAttributes')
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

Http::post('/v1/queries/getCountOfIndexes')
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

Http::post('/v1/queries/getAttributeWidth')
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

Http::post('/v1/queries/createDocument')
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

Http::post('/v1/queries/updateDocument')
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

Http::post('/v1/queries/deleteDocument')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, Adapter $adapter, Response $response) {
        $output = $adapter->deleteDocument($collection, $id);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/getDocument')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->param('queries', [], new ArrayList(new Assoc(MAX_STRING_SIZE), MAX_ARRAY_SIZE), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, array $queries, Adapter $adapter, Response $response) {
        foreach ($queries as &$query) {
            $query = new Query($query['method'], $query['attribute'] ?? '', $query['values'] ?? []);
        }

        $output = $adapter->getDocument($collection, $id, $queries);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/find')
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

Http::post('/v1/queries/sum')
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

Http::post('/v1/queries/count')
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

Http::post('/v1/queries/increaseDocumentAttribute')
    ->groups(['api'])
    ->param('collection', '', new Text(MAX_STRING_SIZE, 0))
    ->param('id', '', new Text(MAX_STRING_SIZE, 0))
    ->param('attribute', '', new Text(MAX_STRING_SIZE, 0))
    ->param('value', null, new FloatValidator(), '', true)
    ->param('min', null, new FloatValidator(), '', true)
    ->param('max', null, new FloatValidator(), '', true)
    ->inject('adapter')
    ->inject('response')
    ->action(function (string $collection, string $id, string $attribute, float $value, ?float $min, ?float $max, Adapter $adapter, Response $response) {
        $output = $adapter->increaseDocumentAttribute($collection, $id, $attribute, $value, $min, $max);

        $response->json([
            'output' => $output
        ]);
    });

Http::post('/v1/queries/createRelationship')
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

Http::post('/v1/queries/updateRelationship')
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

Http::post('/v1/queries/deleteRelationship')
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

<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;

/**
 * MongoDB search: filters, facets, collections, indexes.
 */
class SearchController extends AbstractController
{
    /**
     * @return array{uri: string, db: string}|null
     */
    private function getMongoConfig(): ?array
    {
        $config = Registry::getInstance()->get('config');
        $sm = $config['data']['search_mongodb'] ?? null;
        if (!$sm || empty($sm['enabled']) || empty($sm['database']['uri']) || $sm['database']['db'] === '') {
            return null;
        }
        return [
            'uri' => $sm['database']['uri'],
            'db' => $sm['database']['db'],
        ];
    }

    /**
     * @return \MongoDB\Database|null
     */
    private function getMongoDb()
    {
        $mongoConfig = $this->getMongoConfig();
        if ($mongoConfig === null) {
            return null;
        }
        try {
            return MongoDB::getDatabase($mongoConfig['uri'], $mongoConfig['db']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function indexAction(): void
    {
        $mongoConfig = $this->getMongoConfig();
        $this->render('search/index.php', [
            'title' => 'Search',
            'mongoConfigured' => $mongoConfig !== null,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    public function queryAction(): void
    {
        $db = $this->getMongoDb();
        $formatHtml = $this->get('format') === 'html';
        $emptyResult = [
            'title' => 'Documents',
            'error' => null,
            'documents' => [],
            'collection' => '',
            'total' => 0,
            'pageNum' => 1,
            'totalPages' => 1,
            'perPage' => 50,
            'baseUrl' => $this->baseUrl(),
            'paginationBaseUrl' => '',
            'filterQuery' => '',
        ];
        if ($db === null) {
            $emptyResult['error'] = 'MongoDB not configured or unavailable';
            if ($formatHtml) {
                $this->render('search/query-result.php', $emptyResult);
                return;
            }
            $this->json(['error' => $emptyResult['error']], 400);
            return;
        }
        $collectionName = $this->get('collection');
        $filterQuery = trim((string) $this->get('filter'));
        $limit = (int) ($this->get('limit') ?? $this->get('per_page') ?? 50);
        $limit = min(100, max(1, $limit));
        $pageNum = max(1, (int) $this->get('page_num', 1));
        if ($collectionName === '' || $collectionName === null) {
            $emptyResult['error'] = 'Parameter collection required';
            if ($formatHtml) {
                $this->render('search/query-result.php', $emptyResult);
                return;
            }
            $this->json(['error' => $emptyResult['error']], 400);
            return;
        }
        // Parse filter query (relaxed JSON / MongoDB syntax)
        $filter = [];
        $filterError = null;
        if ($filterQuery !== '') {
            $parsed = $this->parseRelaxedJson($filterQuery);
            if ($parsed === null) {
                $filterError = 'Invalid query syntax. Use MongoDB JSON, e.g. {id_media_object: 12345}';
            } elseif (!is_array($parsed)) {
                $filterError = 'Query must be a JSON object, e.g. {id_media_object: 12345}';
            } else {
                $filter = $parsed;
            }
        }
        if ($filterError !== null) {
            $emptyResult['error'] = $filterError;
            $emptyResult['collection'] = $collectionName;
            $emptyResult['filterQuery'] = $filterQuery;
            if ($formatHtml) {
                $this->render('search/query-result.php', $emptyResult);
                return;
            }
            $this->json(['error' => $filterError], 400);
            return;
        }
        try {
            $collection = $db->selectCollection($collectionName);
            $total = $collection->countDocuments($filter);
            $totalPages = max(1, (int) ceil($total / $limit));
            $offset = ($pageNum - 1) * $limit;
            $cursor = $collection->find($filter, [
                'limit' => $limit,
                'skip' => $offset,
                'sort' => ['_id' => 1],
            ]);
            $documents = [];
            foreach ($cursor as $doc) {
                $documents[] = json_decode(json_encode($doc->bsonSerialize()), true);
            }
            if ($formatHtml) {
                $queryBaseUrl = $this->baseUrl() . 'page=search&action=query&format=html&collection=' . urlencode($collectionName) . '&per_page=' . $limit;
                if ($filterQuery !== '') {
                    $queryBaseUrl .= '&filter=' . urlencode($filterQuery);
                }
                $queryBaseUrl .= '&';
                $this->render('search/query-result.php', [
                    'title' => 'Documents',
                    'error' => null,
                    'documents' => $documents,
                    'collection' => $collectionName,
                    'total' => $total,
                    'pageNum' => $pageNum,
                    'totalPages' => $totalPages,
                    'perPage' => $limit,
                    'baseUrl' => $this->baseUrl(),
                    'paginationBaseUrl' => $queryBaseUrl,
                    'filterQuery' => $filterQuery,
                ]);
                return;
            }
            $this->json(['total' => $total, 'documents' => $documents]);
        } catch (\Throwable $e) {
            $emptyResult['error'] = $e->getMessage();
            $emptyResult['collection'] = $collectionName;
            $emptyResult['filterQuery'] = $filterQuery;
            if ($formatHtml) {
                $this->render('search/query-result.php', $emptyResult);
                return;
            }
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse relaxed JSON (MongoDB shell syntax).
     * Accepts: {key: "val"}, {key: 123}, {"key": "val"}, etc.
     *
     * @return array|null Parsed array or null on failure
     */
    private function parseRelaxedJson(string $input): ?array
    {
        $input = trim($input);
        // Try strict JSON first
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Fix relaxed JSON: add double quotes around unquoted keys
        // Pattern: { key: or , key: where key is a word (with $ prefix allowed)
        $fixed = preg_replace('/([{,])\s*(\$?[a-zA-Z_][a-zA-Z0-9_]*)\s*:/u', '$1"$2":', $input);
        // Replace single-quoted strings with double-quoted
        $fixed = preg_replace("/'/", '"', $fixed);
        $decoded = json_decode($fixed, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return null;
    }

    public function collectionsAction(): void
    {
        $db = $this->getMongoDb();
        $collections = [];
        $error = null;
        if ($db === null) {
            $error = 'MongoDB not configured or unavailable';
        } else {
            try {
                foreach ($db->listCollections() as $collInfo) {
                    $name = $collInfo->getName();
                    try {
                        $count = $db->selectCollection($name)->countDocuments();
                    } catch (\Throwable $e) {
                        $count = null;
                    }
                    $collections[] = ['name' => $name, 'count' => $count];
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('search/collections.php', [
            'title' => 'Collections',
            'collections' => $collections,
            'error' => $error,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    public function indexesAction(): void
    {
        $db = $this->getMongoDb();
        $collectionName = $this->get('collection') ?? '';
        $indexes = [];
        $error = null;
        if ($db === null) {
            $error = 'MongoDB not configured or unavailable';
        } elseif ($collectionName === '') {
            $error = 'Parameter collection required';
        } else {
            try {
                $coll = $db->selectCollection($collectionName);
                foreach ($coll->listIndexes() as $idx) {
                    $indexes[] = [
                        'name' => $idx->getName(),
                        'key' => $idx->getKey(),
                    ];
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('search/indexes.php', [
            'title' => 'Indexes',
            'collection' => $collectionName,
            'indexes' => $indexes,
            'error' => $error,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    public function detailAction(): void
    {
        $db = $this->getMongoDb();
        $collectionName = $this->get('collection') ?? '';
        $id = $this->get('id') ?? '';
        $by = $this->get('by') ?? '';
        $document = null;
        $error = null;
        if ($db === null) {
            $error = 'MongoDB not configured or unavailable';
        } elseif ($collectionName === '' || $id === '') {
            $error = 'Parameters collection and id required';
        } else {
            try {
                $coll = $db->selectCollection($collectionName);
                $doc = null;
                if ($by === '_id') {
                    if (is_numeric($id)) {
                        $doc = $coll->findOne(['_id' => (int) $id]);
                    }
                    if ($doc === null) {
                        $doc = $coll->findOne(['_id' => $id]);
                    }
                    if ($doc === null && preg_match('/^[a-f0-9]{24}$/i', $id)) {
                        $doc = $coll->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                    }
                } else {
                    $doc = $coll->findOne(['id_media_object' => (int) $id]);
                    if ($doc === null && is_numeric($id)) {
                        $doc = $coll->findOne(['_id' => (int) $id]);
                    }
                    if ($doc === null) {
                        $doc = $coll->findOne(['_id' => $id]);
                    }
                    if ($doc === null && preg_match('/^[a-f0-9]{24}$/i', $id)) {
                        $doc = $coll->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                    }
                }
                if ($doc !== null) {
                    $document = json_decode(json_encode($doc->bsonSerialize()), true);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $this->render('search/detail.php', [
            'title' => 'Document',
            'collection' => $collectionName,
            'id' => $id,
            'document' => $document,
            'error' => $error,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    public function reindexAction(): void
    {
        $this->json(['error' => 'Not implemented'], 501);
    }

    private function baseUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base . '?' : '?');
    }
}

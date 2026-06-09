<?php

namespace Pressmind\Search;

use OpenSearch\SymfonyClientFactory;
use Pressmind\Cache\Adapter\Factory;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\FulltextSearch;
use Pressmind\Registry;

class OpenSearch extends AbstractSearch
{
    /**
     * @var string[]
     */
    private $_log = [];

    /**
     * @var string|null
     */
    private $_language;

    /**
     * @var \OpenSearch\Client
     */
    private $_client;

    /**
     * @var string
     */
    private $_search_term;

    /**
     * @var mixed
     */
    private $_config = [];

    /**
     * @var bool
     */
    private $_skip_cache = false;

    /**
     * @var string
     */
    private $_index_name;

    /**
     * @var int
     */
    private $_limit = 100;

    /**
     * @param string $search_term
     * @param string|null $language
     * @param int $limit
     */
    public function __construct($search_term, $language = null, $limit = 100)
    {
        $this->_config = Registry::getInstance()->get('config');
        $this->_addLog('__construct()');
        if (is_null($language) && count($this->_config['data']['languages']['allowed']) > 1) {
            $this->_language = $this->_config ['data']['languages']['default'];
        } elseif (!is_null($language)) {
            $this->_language = $language;
        }
        $opensearchConfig = $this->_config['data']['search_opensearch'];
        $options = [
            'base_uri' => $opensearchConfig['uri'],
            'verify_peer' => false,
        ];
        if (!empty($opensearchConfig['username']) && !empty($opensearchConfig['password'])) {
            $options['auth_basic'] = [$opensearchConfig['username'], $opensearchConfig['password']];
        }
        $this->_client = (new SymfonyClientFactory())->create($options);
        $this->_search_term = $this->sanitizeSearchTerm($search_term);
        $this->_index_name = $this->getIndexTemplateName($language);
        $this->_limit = $limit;
    }

    /**
     * @param $language
     * @return string
     */
    public function getIndexTemplateName($language = null)
    {
        if (empty($language)) {
            return 'index_' . $this->getConfigHash();
        }
        $language = strtolower($language);
        return 'index_' . $this->getConfigHash() . '_' . $language;
    }

    /**
     * @return string
     */
    public function getConfigHash()
    {
        $config = $this->_config['data']['search_opensearch'];
        unset($config['uri'], $config['username'], $config['password']);
        return md5(serialize($config));
    }


    private function _addLog($text)
    {
        $config = Registry::getInstance()->get('config');
        if (isset($config['logging']['enable_advanced_object_log']) && $config['logging']['enable_advanced_object_log'] == true) {
            $now = new \DateTime();
            $this->_log[] = '[' . $now->format(DATE_RFC3339_EXTENDED) . '] ' . $text;
        }
    }

    /**
     * @return string[]
     */
    public function getLog()
    {
        $config = Registry::getInstance()->get('config');
        if (isset($config['logging']['enable_advanced_object_log']) && $config['logging']['enable_advanced_object_log'] == true) {
            return $this->_log;
        }
        return ['Logging is disabled in config (logging.enable_advanced_object_log)'];
    }

    /**
     * @param bool $auto_complete_query
     * @param int $ttl
     * @return array|mixed|void
     * @throws \Exception
     */
    public function getResult($auto_complete_query = false, $ttl = 0)
    {
        $this->_addLog('getResult(): starting query');
        $key = $this->generateCacheKey();
        if (!empty($ttl) && Registry::getInstance()->get('config')['cache']['enabled'] && in_array('OPENSEARCH', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if ($cache_adapter->exists($key)) {
                Writer::write(get_class($this) . ' exec() reading from cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $cache_contents = json_decode($cache_adapter->get($key));
                if (!empty($cache_contents)) {
                    return $cache_contents;
                }
            }
        }
        try {
            $hits = $auto_complete_query ? $this->fetchAutocompleteSuggestions() : $this->fetchAllOpenSearchHits();
            $result = array_map(fn($hit) => $hit['_id'], $hits);
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            exit;
        }
        if (!empty($ttl) && Registry::getInstance()->get('config')['cache']['enabled'] && in_array('OPENSEARCH', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
            $info = new \stdClass();
            $info->type = 'OPENSEARCH';
            $info->classname = self::class;
            $info->method = 'updateCache';
            $info->parameters = ['term' => $this->_search_term, 'limit' => $this->_limit];
            $cache_adapter->add($key, json_encode($result), $info, $ttl);
        }
        $this->_addLog('getResult(): query completed');
        return $result;
    }

    /**
     * @param string $input
     * @return string
     */
    function sanitizeSearchTerm(string $input){
        return trim(preg_replace('/[\x00-\x1F]+/', ' ', FulltextSearch::replaceChars($input)));
    }

    /**
     * Lexical full-text hits (paginated via search_after), same as before vector search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllOpenSearchHits()
    {
        return $this->fetchAllOpenSearchHitsWithScores();
    }

    /**
     * @return list<array{_id: string, _score: float}>
     */
    private function fetchAllOpenSearchHitsWithScores(): array
    {
        $allHits = [];
        $searchAfter = null;
        $shouldClauses = $this->buildLexicalShouldClauses();
        if ($shouldClauses === []) {
            return [];
        }

        while (true) {
            $body = [
                '_source' => false,
                'size' => $this->_limit,
                'sort' => [
                    ['_score' => 'desc'],
                    ['id' => 'asc']
                ],
                'query' => [
                    'bool' => [
                        'should' => $shouldClauses,
                        'minimum_should_match' => 1,
                        'filter' => []
                    ]
                ]
            ];
            if ($searchAfter) {
                $body['search_after'] = $searchAfter;
            }
            $search_params =[
                'index' => $this->_index_name,
                'body' => $body
            ];
            if (!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
                echo '<pre>opensearch: ' . json_encode($search_params) . '</pre>';
            }
            $response = $this->_client->search($search_params);
            $hits = $response['hits']['hits'];
            if (empty($hits)) {
                break;
            }
            $allHits = array_merge($allHits, $hits);
            $searchAfter = end($hits)['sort'] ?? null;
        }
        return $allHits;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLexicalShouldClauses(): array
    {
        $textFields = $this->_getFields('text');
        $keywordFields = $this->_getFields('keyword');

        $shouldClauses = [];
        if (!empty($textFields)) {
            $shouldClauses[] = [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $textFields,
                    'type' => 'best_fields',
                    'operator' => 'and',
                    'fuzziness' => 'AUTO',
                    'prefix_length' => $this->_config['data']['search_opensearch']['prefix_length'] ?? 5
                ]
            ];
        }
        if (!empty($keywordFields)) {
            $shouldClauses[] = [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $keywordFields,
                    'type' => 'best_fields',
                    'operator' => 'and'
                ]
            ];
        }

        if (empty($shouldClauses)) {
            $shouldClauses[] = [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields(),
                    'type' => 'best_fields',
                    'operator' => 'and'
                ]
            ];
        }

        return $shouldClauses;
    }

    /**
     * k-NN only: returns OpenSearch document _id list in relevance order.
     *
     * @param  list<float>  $queryVector
     * @return list<string>
     */
    public function getVectorSearchResultIds(array $queryVector, $k = null): array
    {
        $vecCfg = $this->_config['data']['search_opensearch']['vector'] ?? [];
        $field = (string) ($vecCfg['vector_field'] ?? 'content_vector');
        $k = $k !== null ? (int) $k : (int) ($vecCfg['k'] ?? $this->_limit);
        if ($k < 1) {
            $k = 1;
        }
        $minScore = (float) ($vecCfg['min_score'] ?? 0.0);
        $map = $this->fetchVectorHitsWithScores($queryVector, $field, $k, $minScore);

        if (!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
            echo '<pre>opensearch vector ids: ' . count($map) . ' (min_score=' . $minScore . ')</pre>';
        }

        return array_slice(array_keys($map), 0, $k);
    }

    /**
     * Hybrid lexical + k-NN: weighted score fusion, returns up to $k document _ids.
     *
     * @param  list<float>  $queryVector
     * @return list<string>
     */
    public function getHybridSearchResultIds(string $term, array $queryVector, $k = null): array
    {
        $vecCfg = $this->_config['data']['search_opensearch']['vector'] ?? [];
        $k = $k !== null ? (int) $k : (int) ($vecCfg['k'] ?? $this->_limit);
        if ($k < 1) {
            $k = 1;
        }
        $lw = (float) ($vecCfg['lexical_weight'] ?? 0.4);
        $sw = (float) ($vecCfg['semantic_weight'] ?? 0.6);
        $field = (string) ($vecCfg['vector_field'] ?? 'content_vector');
        $minScore = (float) ($vecCfg['min_score'] ?? 0.0);

        $semMap = $this->fetchVectorHitsWithScores($queryVector, $field, $k, $minScore);
        $term = $this->sanitizeSearchTerm($term);
        if ($term === '') {
            return array_slice(array_keys($semMap), 0, $k);
        }

        $savedTerm = $this->_search_term;
        $this->_search_term = $term;
        $lexHits = $this->fetchAllOpenSearchHitsWithScores();
        $this->_search_term = $savedTerm;

        $lexMap = [];
        foreach ($lexHits as $hit) {
            $id = (string) ($hit['_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $lexMap[$id] = (float) ($hit['_score'] ?? 0.0);
        }

        if ($semMap === [] && $lexMap === []) {
            return [];
        }
        if ($semMap === []) {
            return array_slice(array_keys($lexMap), 0, $k);
        }
        if ($lexMap === []) {
            return array_slice(array_keys($semMap), 0, $k);
        }

        $maxL = max($lexMap) ?: 1.0;
        $maxS = max($semMap) ?: 1.0;
        $allIds = array_unique(array_merge(array_keys($lexMap), array_keys($semMap)));
        $combined = [];
        foreach ($allIds as $id) {
            $nl = ($lexMap[$id] ?? 0.0) / $maxL;
            $ns = ($semMap[$id] ?? 0.0) / $maxS;
            $combined[$id] = $lw * $nl + $sw * $ns;
        }
        arsort($combined, SORT_NUMERIC);

        return array_slice(array_keys($combined), 0, $k);
    }

    /**
     * @param  list<float>  $queryVector
     * @return array<string, float>  id => raw _score
     */
    private function fetchVectorHitsWithScores(array $queryVector, string $field, int $k, float $minScore = 0.0): array
    {
        $body = [
            '_source' => false,
            'size' => $k,
            'query' => [
                'knn' => [
                    $field => [
                        'vector' => $queryVector,
                        'k' => $k,
                    ],
                ],
            ],
        ];
        $search_params = [
            'index' => $this->_index_name,
            'body' => $body,
        ];
        $response = $this->_client->search($search_params);
        $hits = $response['hits']['hits'] ?? [];
        $map = [];
        foreach ($hits as $hit) {
            $id = (string) ($hit['_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $score = (float) ($hit['_score'] ?? 0.0);
            if ($minScore > 0.0 && $score < $minScore) {
                continue;
            }
            $map[$id] = $score;
        }

        return $map;
    }

    /**
     * @return array
     */
    public function fetchAutocompleteSuggestions(): array
    {
        $body = [
            '_source' => ['id'], // oder relevante Felder für die Suggestion
            'size' => 10,
            'sort' => [['_score' => 'desc']],
            'query' => [
                'multi_match' => [
                    'query' => $this->_search_term,
                    'fields' => $this->_getFields('text'),
                    'type' => 'phrase_prefix',
                    'operator' => 'and'
                ]
            ]
        ];
        $search_params = [
            'index' => $this->_index_name,
            'body' => $body
        ];
        $response = $this->_client->search($search_params);
        return $response['hits']['hits'] ?? [];
    }

    /**
     * @param string|null $filter
     * @return array
     */
    private function _getFields($filter = null)
    {
        if(is_null($filter)) {
            $filter = ['text', 'keyword'];
        } elseif (is_string($filter)) {
            $filter = [$filter];
        }
        $fields = [];
        foreach ($this->_config['data']['search_opensearch']['index'] as $field => $field_config) {
            if(!empty($field_config['type']) && !in_array($field_config['type'], $filter)) {
                continue;
            }
            if (!isset($field_config['boost']) || !is_numeric($field_config['boost'])) {
                $field_config['boost'] = 1; // default boost
            }
            $fields[] = $field . '^' . $field_config['boost'];
        }
        return $fields;
    }

    /**
     * @param null $key
     * @param null $output
     * @param null $preview_date
     * @param int[] $allowed_visibilities
     * @return string|null
     * @throws \Exception
     */
    public function updateCache($key = null, $output = null, $preview_date = null, $allowed_visibilities = [30])
    {
        $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if (is_null($key)) {
            $key = $this->generateCacheKey('', $output, $preview_date, $allowed_visibilities);
        }
        $info = $this->getCacheInfo($key);
        $params = isset($info['info']) && !empty($info['info']->parameters) ? $info['info']->parameters : null;
        if (!is_null($params)) {
            try {
                $db = MongoDB::getDatabase($this->_db_uri, $this->_db_name);
                $collection = $db->{$this->_collection_name};
                $result = $collection->aggregate($params->aggregate)->toArray()[0];
                $info = new \stdClass();
                $info->type = 'MONGODB';
                $info->classname = self::class;
                $info->method = 'updateCache';
                $info->parameters = ['aggregate' => $params->aggregate];
                Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $cache_adapter->add($key, json_encode($result), $info);
                return $key . ': ' . $params->aggregate;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return null;
    }

    /**
     * @param string $add
     * @return string
     */
    public function generateCacheKey()
    {
        return 'OPENSEARCH:' . md5(serialize([$this->_search_term, $this->_index_name, $this->_language, $this->_limit]));
    }
}

<?php

namespace Pressmind\Search;

use OpenSearch\ClientBuilder;
use Pressmind\Cache\Adapter\Factory;
use Pressmind\Log\Writer;
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
        $this->_client = ClientBuilder::create()->setHosts([$this->_config['data']['search_opensearch']['uri']]);
        if (!empty($this->_config['data']['search_opensearch']['username']) && !empty($this->_config['data']['search_opensearch']['password'])) {
            $this->_client->setBasicAuthentication($this->_config['data']['search_opensearch']['username'], $this->_config['data']['search_opensearch']['password']);
        }
        $this->_client = $this->_client->build();
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
            $info->parameters = ['term' => $this->_search_term, 'limit' => $limit];
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
        return trim(preg_replace('/[\x00-\x1F]+/', ' ', $input));
    }

    /**
     * @return array
     */
    public function fetchAllOpenSearchHits()
    {
        $allHits = [];
        $searchAfter = null;
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
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $this->_search_term,
                                    'fields' => $this->_getFields(),
                                    'type' => 'best_fields',
                                    'operator' => 'and',
                                    'fuzziness' => 'AUTO'
                                ]
                            ]
                        ],
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
                echo '<pre>' . json_encode($search_params) . '</pre>';
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
                $client = new \MongoDB\Client($this->_db_uri);
                $db_name = $this->_db_name;
                $db = $client->$db_name;
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

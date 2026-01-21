<?php

namespace Pressmind\Search\OpenSearch;

use OpenSearch\ClientBuilder;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;

class AbstractIndex
{
    /**
     * @var MediaObject null
     */
    public $mediaObject = null;

    /**
     * @var \OpenSearch\Client
     */
    public $client;

    /**
     * @var \MongoDB\Collection
     */
    public $collection;

    /**
     * @var array
     */
    protected $_config;


    /**
     * @var array
     */
    protected $_allowed_visibilities;

    /**
     * @var array
     */
    protected $_allowed_fulltext_fields;

    /**
     * @var int
     */
    protected $_number_of_shards = 1;

    /**
     * @var int
     */
    protected $_number_of_replicas = 0;

    /**
     * @var array
     */
    protected $_languages = [];

    public function __construct()
    {
        $this->_config = Registry::getInstance()->get('config')['data']['search_opensearch'];
        $this->_allowed_visibilities = Registry::getInstance()->get('config')['data']['media_types_allowed_visibilities'];
        $this->client = ClientBuilder::create()->setHosts([$this->_config['uri']]);
        if (!empty($this->_config['username']) && !empty($this->_config['password'])) {
            $this->client->setBasicAuthentication($this->_config['username'], $this->_config['password']);
        }
        $this->client->setSSLVerification(false);
        $this->client = $this->client->build();
        $this->_number_of_shards = isset($this->_config['number_of_shards']) ? $this->_config['number_of_shards'] : 1;
        $this->_number_of_replicas = isset($this->_config['number_of_replicas']) ? $this->_config['number_of_replicas'] : 0;
        $this->_languages = $this->getLanguages();
    }

    /**
     * @param string $html
     * @return string
     */
    public function htmlToFulltext(string $html): string
    {
        $html = preg_replace('/<(br|\/br|p|\/p|div|\/div)[^>]*>/i', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
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
     * @return void
     */
    public function deleteAllIndexesThatNotMatchConfigHash()
    {
        $indexes = $this->getIndexes();
        $hash = $this->getConfigHash();
        foreach ($indexes as $index) {
            if (strpos($index['index'], 'index_') !== 0) {
                continue;
            }
            if (strpos($index['index'], 'index_' . $hash) === false) {
                $this->client->indices()->delete(['index' => $index['index']]);
                $this->client->indices()->deleteTemplate(['name' => $index['index']]);
            }
        }
    }

    /**
     * @param $language
     * @return string
     */
    public function getAnalyzerNameForLanguage($language = null)
    {
        $language = strtolower((string)$language);
        $map = [
            'de' => 'german_default',
            'en' => 'english_default'
        ];
        if (isset($map[$language])) {
            return $map[$language];
        }
        return 'german_default';
    }

    /**
     * @param $string
     * @param $language
     * @return mixed|string
     */
    public function getStringWithLanguageSuffix($string, $language = null)
    {
        $language = strtolower((string)$language);
        if (empty($language)) {
            $language = 'de';
        }
        $suffix = '_' . $language;
        if (strpos($string, $suffix) === false) {
            return $string . $suffix;
        }
        return $string;
    }

    /**
     * @param $language
     * @return array[]
     */
    public function getDefaultFilterForLanguage($language = null)
    {
        $language = strtolower((string)$language);
        $map = [
            'de' => [
                'german_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'light_german'
                ],
                'german_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_german_'
                ],
            ],
            'en' => [
                'english_stemmer' => [
                    'type' => 'stemmer',
                    'language' => 'light_english'
                ],
                'english_stop' => [
                    'type' => 'stop',
                    'stopwords' => '_english_'
                ],
            ],
        ];
        if (isset($map[$language])) {
            return $map[$language];
        }
        return $map['de'];
    }

    /**
     * @param $language
     * @return array
     */
    public function getDefaultAnalyzerForLanguage($language = null)
    {
        $language = strtolower((string)$language);
        $map = [
            'de' => [
                'german_default' => [
                    'type' => 'standard',
                    'stopwords' => '_german_'
                ],
                'autocomplete_de' => [
                    'type' => 'custom',
                    'tokenizer' => 'autocomplete_tokenizer',
                    'filter' => [
                        'lowercase',
                        'german_stop',
                        'german_stemmer'
                    ]
                ],
                'autocomplete_search_de' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'german_stop',
                        'german_stemmer'
                    ]
                ]
            ],
            'en' => [
                'english_default' => [
                    'type' => 'standard',
                    'stopwords' => '_english_'
                ],
                'autocomplete_en' => [
                    'type' => 'custom',
                    'tokenizer' => 'autocomplete_tokenizer',
                    'filter' => [
                        'lowercase',
                        'english_stop',
                        'english_stemmer'
                    ]
                ],
                'autocomplete_search_en' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => [
                        'lowercase',
                        'english_stop',
                        'english_stemmer'
                    ]
                ]
            ],
        ];
        if (isset($map[$language])) {
            return $map[$language];
        }
        return $map['de'];
    }

    /**
     * @param $templateName
     * @return bool
     */
    public function indexExists($templateName)
    {
        return $this->client->indices()->exists(['index' => $templateName]);
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        $languages = [];
        foreach ($this->_config['index'] as $property) {
            foreach ($property['object_type_mapping'] as $id_object_type => $fields) {
                foreach ($fields as $field) {
                    if (!empty($field['language'])) {
                        $languages[] = strtolower($field['language']);
                    }
                }
            }
        }
        $languages = array_unique($languages);
        if (empty($languages)) {
            $languages = [null];
        }
        return $languages;
    }

    /**
     * @return array
     */
    public function getIndexes()
    {
        return $this->client->cat()->indices();
    }

    /**
     * @return array
     */
    public function getAllRequiredObjectTypes()
    {
        $object_types = [];
        foreach ($this->_config['index'] as $property) {
            foreach ($property['object_type_mapping'] as $id_object_type => $fields) {
                $object_types[] = $id_object_type;
            }
        }
        return array_unique($object_types);
    }

    /**
     * @return string
     */
    public function getConfigHash()
    {
        $config = $this->_config;
        unset($config['uri'], $config['username'], $config['password']);
        return md5(serialize($config));
    }

}

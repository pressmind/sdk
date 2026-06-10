<?php

namespace Pressmind\Search\OpenSearch;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\CategoryTree;
use Pressmind\ORM\Object\FulltextSearch;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Search\Embedding\EmbeddingCache;
use Pressmind\Search\Embedding\ProviderFactory;

class Indexer extends AbstractIndex
{
    /**
     * @param string|int|array<int|string> $id_media_objects
     * @throws \Exception
     */
    public function deleteMediaObject($id_media_objects): void
    {
        if (empty($id_media_objects)) {
            return;
        }
        if (!is_array($id_media_objects)) {
            $id_media_objects = array_map('intval', explode(',', (string)$id_media_objects));
        }

        foreach ($id_media_objects as $id_media_object) {
            $id_media_object = (int)$id_media_object;
            if ($id_media_object <= 0) {
                continue;
            }
            foreach ($this->_languages as $language) {
                $params = [
                    'index' => $this->getIndexTemplateName($language),
                    'id' => $id_media_object
                ];
                try {
                    $this->client->delete($params);
                } catch (\OpenSearch\Common\Exceptions\Missing404Exception|\OpenSearch\Exception\NotFoundHttpException $e) {
                    // Document or index is already gone.
                }
            }
        }
    }

    public function createIndexes()
    {
        $ids = [];
        $object_types = $this->getAllRequiredObjectTypes();
        foreach ($object_types as $id_object_type) {
            $mediaObjects = MediaObject::listAll(['id_object_type' => $id_object_type]);
            foreach ($mediaObjects as $mediaObject) {
                echo $mediaObject->id . "\n";
                $ids[] = $mediaObject->id;
            }
        }
        $this->upsertMediaObject($ids);
    }

    public function createIndexTemplates()
    {
        foreach ($this->_languages as $language) {
            $indexTemplateName = $this->getIndexTemplateName($language);
            $params = [
                'name' => $indexTemplateName,
                'body' => [
                    'index_patterns' => [$this->getIndexTemplateName($language)],
                    'settings' => [
                        'number_of_shards' => $this->_number_of_shards,
                        'number_of_replicas' => $this->_number_of_replicas,
                        'analysis' => [
                            'tokenizer' => [
                               'autocomplete_tokenizer' => [
                                   'type' => 'edge_ngram',
                                   'min_gram' => 2,
                                   'max_gram' => 20,
                                   'token_chars' => [
                                       'letter',
                                       'digit'
                                   ]
                               ]
                            ],
                            'filter' => $this->getDefaultFilterForLanguage($language),
                            'analyzer' => $this->getDefaultAnalyzerForLanguage($language),
                        ]
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'id_object_type' => ['type' => 'integer'],
                            'fulltext' => ['type' => 'text', 'analyzer' => $this->getAnalyzerNameForLanguage($language)],
                        ]
                    ]
                ]
            ];
            $vectorCfg = $this->_config['vector'] ?? [];
            if (! empty($vectorCfg['enabled'])) {
                $params['body']['settings']['index']['knn'] = true;
                $dims = (int) ($vectorCfg['dimensions'] ?? 1536);
                $space = (string) ($vectorCfg['space_type'] ?? 'cosinesimil');
                $fieldName = (string) ($vectorCfg['vector_field'] ?? 'content_vector');
                $params['body']['mappings']['properties'][$fieldName] = [
                    'type' => 'knn_vector',
                    'dimension' => $dims,
                    'method' => [
                        'name' => 'hnsw',
                        'space_type' => $space,
                    ],
                ];
            }
            foreach ($this->_config['index'] as $property_name => $property) {
                if (empty($property['object_type_mapping'])) {
                    continue;
                }
                foreach ($property['object_type_mapping'] as $id_object_type => $fields) {
                    if (empty($fields)) {
                        continue;
                    }
                    foreach ($fields as $field) {
                        if (empty($field['language']) || strtolower($field['language']) == $language) {
                            if ($property['type'] == 'text' || empty($property['type'])) {
                                $params['body']['mappings']['properties'][$property_name] = [
                                    'type' => 'text',
                                    'analyzer' => $this->getStringWithLanguageSuffix('autocomplete', $language),
                                    'search_analyzer' => $this->getStringWithLanguageSuffix('autocomplete_search', $language),
                                ];
                            }
                            if ($property['type'] == 'keyword') {
                                $params['body']['mappings']['properties'][$property_name] = [
                                    'type' => 'keyword',
                                ];
                            }
                            break;
                        }
                    }
                }
            }
            $response = $this->client->indices()->putTemplate($params);
            if(empty($response['acknowledged'])){
                throw new \Exception("Failed to create index template: " . $indexTemplateName . ". Response: " . json_encode($response));
            }
            if (!$this->indexExists($indexTemplateName)) {
                $response = $this->client->indices()->create([
                    'index' => $indexTemplateName
                ]);
                if(empty($response['acknowledged'])){
                    throw new \Exception("Failed to create index: " . $indexTemplateName . ". Response: " . json_encode($response));
                }
            }
        }
        $this->deleteAllIndexesThatNotMatchConfigHash();
    }

    public function allIndexTemplatesExist(): bool
    {
        foreach ($this->_languages as $language) {
            $indexTemplateName = $this->getIndexTemplateName($language);
            if (!$this->indexExists($indexTemplateName)) {
                echo "Index template {$indexTemplateName} does not exist.\n";
                return false;
            }
        }
        echo "All index templates exist.\n";
        return true;
    }

    public function upsertMediaObject($id_media_objects)
    {
        if ($this->allIndexTemplatesExist() === false) {
            $this->createIndexTemplates();
        }
        if (!is_array($id_media_objects)) {
            $id_media_objects = [$id_media_objects];
        }
        $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);
        $ids = [];
        $maxRetries = (int)($this->_config['max_retries'] ?? 2);
        foreach ($mediaObjects as $mediaObject) {
            echo "Processing media object ID {$mediaObject->id}...\n";
            foreach ($this->_languages as $language) {
                $document = $this->createIndex($mediaObject->id, $language);
                if ($document === false) {
                    continue;
                }
                $params = [
                    'index' => $this->getIndexTemplateName($language),
                    'id' => $mediaObject->id,
                    'body' => $document
                ];
                $response = $this->indexWithRetry($params, $mediaObject->id, $language, $maxRetries);
                if ($response === null) {
                    continue;
                }
                if (isset($response['result']) && $response['result'] === 'created') {
                    echo "Index for media object ID {$mediaObject->id} created successfully in language {$language}.\n";
                } elseif (isset($response['result']) && $response['result'] === 'updated') {
                    echo "Index for media object ID {$mediaObject->id} updated successfully in language {$language}.\n";
                } else {
                    echo "Failed to index media object ID {$mediaObject->id} in language {$language}.\n";
                }
                $ids[] = $mediaObject->id;
            }
        }
        // Orphans
        foreach ($id_media_objects as $id_media_object) {
            if (!empty($ids) && in_array($id_media_object, $ids)) {
                continue;
            }
            $params = [
                'index' => $this->getIndexTemplateName($language),
                'id' => $id_media_object
            ];
            try {
                $this->client->delete($params);
            } catch (\Exception $e) {
                // soft delete, if index does not exist
            }
        }

    }

    private function indexWithRetry(array $params, int $id, ?string $language, int $maxRetries): ?array
    {
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    echo "Retrying OpenSearch index for media object ID {$id} (attempt " . ($attempt + 1) . ")...\n";
                    $this->reconnectOpenSearchClient();
                    usleep(500000);
                }
                return $this->client->index($params);
            } catch (\Exception $e) {
                echo "OpenSearch error for media object ID {$id} in language {$language}: " . $e->getMessage() . "\n";
                if ($attempt >= $maxRetries) {
                    echo "Skipping media object ID {$id} after " . ($maxRetries + 1) . " attempts.\n";
                    return null;
                }
            }
        }
        return null;
    }


    public function createIndex($idMediaObject, $language)
    {
        // Base row only; `data` is loaded lazily via getDataForLanguage (avoids insurance_group / full ORM graph).
        $mediaObject = new MediaObject($idMediaObject, false, true);
        $mediaObjectData = $mediaObject->getDataForLanguage($language);
        $searchObject = new \stdClass();
        $searchObject->id_object_type = $mediaObject->id_object_type;
        $searchObject->fulltext = FulltextSearch::getFullTextWords($mediaObject->id, $mediaObject->id_object_type, $language);
        $fields = $this->getFields($language, $mediaObject->id_object_type);
        if (empty($fields)) {
            echo "No fields found for media object ID {$idMediaObject} in language {$language}.\n";
            return false;
        }
        foreach ($fields as $property_name => $property) {
            $string = '';
            $field_name = $property['name'];
            if ($field_name === 'id') {
                $searchObject->{$property_name} = (string) $mediaObject->id;
                continue;
            }
            if (in_array($field_name, ['code', 'name', 'tags'])) {
                if (isset($mediaObject->{$field_name}) && !empty($mediaObject->{$field_name})) {
                    $string = $mediaObject->{$field_name};
                }
            } else {
                if (isset($mediaObjectData->{$field_name}) && !empty($mediaObjectData->{$field_name})) {
                    if (is_string($mediaObjectData->{$field_name})) {
                        $string = $this->htmlToFulltext($mediaObjectData->{$field_name});
                    } elseif (is_array($mediaObjectData->{$field_name})) {
                        /**
                         * @var MediaObject\DataType\Categorytree[] $trees
                         */
                        $trees = $mediaObjectData->{$field_name};
                        $items = [];
                        foreach ($trees as $treeItem) {
                            $items[] = $treeItem->item->name;
                        }
                        $string = implode(' ', $items);
                    }
                }
            }
            $searchObject->{$property_name} = FulltextSearch::replaceChars($string);
        }
        $vectorCfg = $this->_config['vector'] ?? [];
        if (! empty($vectorCfg['enabled'])) {
            $this->attachContentVector($searchObject, $vectorCfg);
        }

        return $searchObject;
    }

    /**
     * @param  \stdClass  $searchObject
     * @param  array<string, mixed>  $vectorCfg
     */
    private function attachContentVector($searchObject, array $vectorCfg): void
    {
        $embedText = $this->buildEmbeddingSourceText($searchObject, $vectorCfg);
        $minLen = (int) ($vectorCfg['min_text_length'] ?? 50);
        if (strlen($embedText) < $minLen) {
            $field = (string) ($vectorCfg['vector_field'] ?? 'content_vector');
            $searchObject->{$field} = null;

            return;
        }
        try {
            $provider = ProviderFactory::create($vectorCfg);
            $model = (string) ($vectorCfg['model'] ?? 'text-embedding-3-small');
            $dims = (int) ($vectorCfg['dimensions'] ?? 1536);
            $field = (string) ($vectorCfg['vector_field'] ?? 'content_vector');
            $cacheEnabled = ! empty($vectorCfg['cache']['enabled']);
            $cache = null;
            if ($cacheEnabled) {
                $cache = EmbeddingCache::fromRegistry();
                $cache->ensureIndexes();
                $cached = $cache->getDocumentEmbedding($embedText, $model, $dims);
                if ($cached !== null) {
                    $searchObject->{$field} = $cached;

                    return;
                }
            }
            $vector = $provider->embed($embedText);
            $searchObject->{$field} = $vector;
            if ($cacheEnabled && $cache !== null) {
                $cache->putDocumentEmbedding($embedText, $model, $dims, $vector);
            }
        } catch (\Throwable $e) {
            Writer::write(
                'OpenSearch Indexer: embedding failed for media object: ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'opensearch',
                Writer::TYPE_WARNING
            );
            $field = (string) ($vectorCfg['vector_field'] ?? 'content_vector');
            $searchObject->{$field} = null;
        }
    }

    /**
     * @param  \stdClass  $searchObject
     * @param  array<string, mixed>  $vectorCfg
     */
    private function buildEmbeddingSourceText($searchObject, array $vectorCfg): string
    {
        $source = isset($vectorCfg['text_source']) ? (string) $vectorCfg['text_source'] : 'fulltext';
        if ($source === '' || $source === 'fulltext') {
            return (string) ($searchObject->fulltext ?? '');
        }
        $parts = array_map('trim', explode(',', $source));
        $buf = [];
        foreach ($parts as $prop) {
            if ($prop === '') {
                continue;
            }
            if (isset($searchObject->{$prop}) && is_string($searchObject->{$prop})) {
                $buf[] = $searchObject->{$prop};
            }
        }

        return implode(' ', $buf);
    }

    public function getFields($language, $id_object_type)
    {
        $fields = [];
        if (isset($this->_config['index']) && is_array($this->_config['index'])) {
            foreach ($this->_config['index'] as $property_name => $property) {
                if (isset($property['object_type_mapping'][$id_object_type]) && is_array($property['object_type_mapping'][$id_object_type])) {
                    foreach ($property['object_type_mapping'][$id_object_type] as $field) {
                        if (empty($field['language']) || strtolower($field['language']) == strtolower($language ?? '')) {
                            $fields[$property_name] = $field['field'];
                        }
                    }
                }
            }
        }
        return $fields;
    }

}

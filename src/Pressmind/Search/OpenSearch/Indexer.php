<?php

namespace Pressmind\Search\OpenSearch;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\CategoryTree;
use Pressmind\ORM\Object\FulltextSearch;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

class Indexer extends AbstractIndex
{

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
                $response = $this->client->index($params);
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


    public function createIndex($idMediaObject, $language)
    {
        $mediaObject = new MediaObject($idMediaObject, true, true);
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
        return $searchObject;
    }

    public function getFields($language, $id_object_type)
    {
        $fields = [];
        if (isset($this->_config['index']) && is_array($this->_config['index'])) {
            foreach ($this->_config['index'] as $property_name => $property) {
                if (isset($property['object_type_mapping'][$id_object_type]) && is_array($property['object_type_mapping'][$id_object_type])) {
                    foreach ($property['object_type_mapping'][$id_object_type] as $field) {
                        if (empty($field['language']) || strtolower($field['language']) == strtolower($language)) {
                            $fields[$property_name] = $field['field'];
                        }
                    }
                }
            }
        }
        return $fields;
    }

}
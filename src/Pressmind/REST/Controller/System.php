<?php

namespace Pressmind\REST\Controller;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\ObjectdataTag;
use Pressmind\Registry;
use Pressmind\REST\Client;

class System {

    public function updateTags($parameters)
    {
        if(!isset($parameters['id_object_type'])) {
            throw new \Exception('Parameter id_object_tyep is missing');
        }
        $client = new Client();
        $response = $client->sendRequest('ObjectType', 'getById', ['ids' => $parameters['id_object_type']]);
        if(is_array($response->result) && isset($response->result[0]->fields) && is_array($response->result[0]->fields)) {
            /**@var AdapterInterface $db */
            $db = Registry::getInstance()->get('db');
            $db->delete('pmt2core_objectdata_tags', ['id_object_type = ?', $parameters['id_object_type']]);
            foreach ($response->result[0]->fields as $field) {
                if (isset($field->sections) && is_array($field->sections)) {
                    foreach ($field->sections as $section) {
                        if (isset($section->tags) && is_array($section->tags)) {
                            foreach ($section->tags as $tag_name) {
                                $tag = new ObjectdataTag();
                                $tag->objectdata_column_name = $field->var_name . '_' . HelperFunctions::human_to_machine($section->name);
                                $tag->tag_name = $tag_name;
                                $tag->id_object_type = $parameters['id_object_type'];
                                $tag->create();
                            }
                        }
                    }
                }
            }
        }
        return ['success' => true, 'msg' => 'tags for Object type ID: ' . $parameters['id_object_type'] . ' successfully updated'];
    }
}

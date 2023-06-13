<?php

namespace Pressmind\Search\MongoDB;

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
     * @var \MongoDB\Database
     */
    public $db;

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

    public function __construct() {
        $this->_config = Registry::getInstance()->get('config')['data']['search_mongodb'];
        $this->_allowed_visibilities = Registry::getInstance()->get('config')['data']['media_types_allowed_visibilities'];
        $this->_allowed_fulltext_fields = Registry::getInstance()->get('config')['data']['media_types_fulltext_index_fields'];
        $uri = $this->_config['database']['uri'];
        $db_name = $this->_config['database']['db'];
        $client = new \MongoDB\Client($uri);
        $this->db = $client->$db_name;
    }

    /**
     * @param $collection_name
     * @return bool|void
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIfNotExists($collection_name)
    {
        foreach($this->db->listCollections() as $collection){
            if($collection['name'] == $collection_name){
                return true;
            }else{
                $this->db->createCollection($collection_name, ['collation' => [ 'locale' => 'de' ]]);
                $this->createCollectionIndex($collection_name);
                return true;
            }
        }
    }

    /**
     * @return void
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionsIfNotExists()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIfNotExists($collection_name);
            }
        }
    }


    /**
     * Create the required indexes for each collection
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndexes()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIndex($collection_name);
            }
        }
    }

    public function flushCollections()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->db->$collection_name->dropIndexes();
                $this->flushCollection($collection_name);
            }
        }
    }

    /**
     * @param $collection_name
     * @return void
     */
    public function flushCollection($collection_name)
    {
        $this->db->$collection_name->deleteMany([]);
    }

}

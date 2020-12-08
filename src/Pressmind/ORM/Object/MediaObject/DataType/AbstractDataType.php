<?php


namespace Pressmind\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
abstract class AbstractDataType extends AbstractObject
{
    protected $_id_media_object;
    protected $_section_name;
    protected $_data;
    protected $_definition = [];

    public function __construct($idMediaObject = null, $sectionName = null)
    {
        $this->_id_media_object = $idMediaObject;
        $this->_section_name = $sectionName;
        $this->load();
    }

    public function __get($name)
    {
        if(is_null($this->_section_name)) {
            $this->_section_name = Registry::getInstance()->get('defaultSectionName');
        }
    }

    public function load()
    {
        $this->_data = [];
    }
}

<?php

namespace Pressmind\ORM\Object\MediaType;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

class AbstractMediaType extends AbstractObject
{
    public function read($pIdMediaObject, $pLanguage = null)
    {
        if(is_null($pLanguage)) {
            $conf = Registry::getInstance()->get('config');
            $pLanguage = $conf['data']['languages']['default'];
        }
        if ($pIdMediaObject != 0 && !empty($pIdMediaObject)) {
            $query = "SELECT * FROM " .
                $this->getDbTableName() .
                " WHERE id_media_object = ? AND language = ?";
            $dataset = $this->_db->fetchRow($query, [$pIdMediaObject, $pLanguage]);
            $this->fromStdClass($dataset);
        }
    }
}

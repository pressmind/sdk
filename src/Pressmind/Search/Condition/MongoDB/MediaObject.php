<?php

namespace Pressmind\Search\Condition\MongoDB;

class MediaObject
{
    /**
     * @var integer
     */
    private $_id_media_object;

    /**
     * @param integer $idMediaObject
     */
    public function __construct($idMediaObject)
    {
        $this->_id_media_object = $idMediaObject;
    }

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'id_media_object' => $this->_id_media_object
            ];
        }
        return null;
    }
}

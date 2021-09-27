<?php

namespace Pressmind\Search\Condition\MongoDB;

class MediaObject
{
    /**
     * @var integer
     */
    private $_id_media_object;

    /**
     * @param array|int|string $idMediaObject
     */
    public function __construct($idMediaObject)
    {
        if(!is_array($idMediaObject)){
            $idMediaObject = [(int)$idMediaObject];
        }
        $this->_id_media_object = $idMediaObject;
    }

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'id_media_object' => [
                    '$in' => $this->_id_media_object
                ]
            ];
        }
        return null;
    }
}

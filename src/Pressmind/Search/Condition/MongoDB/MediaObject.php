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

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * @return int[]
     */
    public function getValue(){
        return $this->_id_media_object;
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return [
                'id_media_object' => [
                    '$in' => $this->_id_media_object
                ]
            ];
        }
        return null;
    }
}

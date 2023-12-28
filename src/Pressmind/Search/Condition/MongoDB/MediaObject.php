<?php

namespace Pressmind\Search\Condition\MongoDB;

class MediaObject
{
    /**
     * @var integer
     */
    private $_id_media_object;
    private $_in = [];
    private $_not_in = [];

    /**
     * @param array|int|string $idMediaObject
     */
    public function __construct($idMediaObject)
    {
        if(!is_array($idMediaObject)){
            $idMediaObject = [(int)$idMediaObject];
        }
        foreach($idMediaObject as $id){
            if($id > 0){
                $this->_in[] = (int)$id;
            } else {
                $this->_not_in[] = (int)abs($id);
            }
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

            if(!empty($this->_in) && empty($this->_not_in)) {
                return [
                    'id_media_object' => [
                        '$in' => $this->_in
                    ]
                ];
            }
            if(empty($this->_in) && !empty($this->_not_in)) {
                return [
                    'id_media_object' => [
                        '$nin' => $this->_not_in
                    ]
                ];
            }
            if(!empty($this->_in) && !empty($this->_not_in)) {
                return [
                    '$and' => [
                        [
                            'id_media_object' => [
                                '$in' => $this->_in
                            ]
                        ],
                        [
                            'id_media_object' => [
                                '$nin' => $this->_not_in
                            ]
                        ]
                    ]
                ];
            }
        }
        return null;
    }
}

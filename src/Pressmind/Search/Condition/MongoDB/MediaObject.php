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
            $id = (int)$id;
            if($id > 0){
                $this->_in[] = $id;
            } elseif($id < 0) {
                $this->_not_in[] = (int)abs($id);
            }
            // id === 0 is skipped: used for "match no documents" (e.g. empty OpenSearch result)
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
            // empty _in and _not_in: e.g. MediaObject([0]) for "no OpenSearch hits" -> match no documents
            if(empty($this->_in) && empty($this->_not_in)) {
                return [
                    'id_media_object' => [
                        '$in' => []
                    ]
                ];
            }
        }
        return null;
    }
}

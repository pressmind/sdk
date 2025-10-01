<?php


namespace Pressmind\ORM\Object;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\Registry;

/**
 * Class Search
 * @property integer $id
 * @property integer $id_media_object
 * @property string $var_name
 * @property string $language
 * @property string $fulltext_values
 */
class FulltextSearch extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_fulltext_search',
            'primary_key' => 'id',
            'storage_engine' => 'myisam'
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'id_media_object' => [
                'title' => 'Id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'var_name' => [
                'title' => 'Variable Name',
                'name' => 'var_name',
                'type' => 'varchar',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'var_name' => 'index'
                ]
            ],
            'language' => [
                'title' => 'Variable Name',
                'name' => 'language',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'language' => 'index'
                ]
            ],
            'fulltext_values' => [
                'title' => 'Variable Name',
                'name' => 'fulltext_values',
                'type' => 'longtext',
                'required' => false,
                'filters' => NULL,
                'index' => [
                    'fulltext_values' => 'fulltext'
                ]
            ],
        ]
    ];

    /**
     * @param $id_media_object
     * @param $id_object_type
     * @param $language
     * @return string
     * @throws \Exception
     */
    public static function getFullTextWords($id_media_object, $id_object_type, $language = null)
    {
        $text = [];
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $allowed_fulltext_fields = Registry::getInstance()->get('config')['data']['media_types_fulltext_index_fields'];
        $query = "SELECT fulltext_values from pmt2core_fulltext_search WHERE id_media_object = ? AND var_name = ?";
        $param = [$id_media_object, 'fulltext'];
        if (!empty($language)) {
            $query .= " AND language = ?";
            $param[] = $language;
        }
        $result = $db->fetchAll($query, $param);
        if (is_array($result)) {
            foreach ($result as $row) {
                $text[] = $row->fulltext_values;
            }
        }
        if (!empty($allowed_fulltext_fields[$id_object_type])) {
            $allowed_fields = '"' . implode('","', array_values($allowed_fulltext_fields[$id_object_type])) . '"';
            $query = 'select fl.fulltext_values from pmt2core_media_object_object_links ol
                        left join pmt2core_fulltext_search fl on (fl.id_media_object = ol.id_media_object_link)
                        where ol.id_media_object = ? and fl.var_name = ? and ol.var_name in(' . $allowed_fields . ')';
            $param = [$id_media_object, 'fulltext'];
            if (!empty($language)) {
                $query .= " AND fl.language = ?";
                $param[] = $language;
            }
            $result = $db->fetchAll($query, $param);
            if (is_array($result)) {
                foreach ($result as $row) {
                    $text[] = $row->fulltext_values;
                }
            }
        }
        return implode(' ', $text);
    }

    /**
     * @param $str
     * @return string
     */
    public static function replaceChars($str){
        $search = ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'];
        $replace = ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'];
        return str_replace($search, $replace, $str);
    }

}

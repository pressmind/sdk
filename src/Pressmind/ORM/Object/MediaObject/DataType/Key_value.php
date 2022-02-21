<?php


namespace Pressmind\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Key_value\Row;

/**
 * Class Key_value
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $language
 * @property string $var_name
 * @property Row[] $rows
 */
class Key_value extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_key_value',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
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
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
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
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'section_name' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'language' => [
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'language' => 'index'
                ]
            ],
            'var_name'  => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
                'index' => [
                    'var_name' => 'index'
                ]
            ],
            'rows' => [
                'title' => 'rows',
                'name' => 'rows',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Key_value\\Row',
                    'related_id' => 'id_key_value',
                    'on_save_related_properties' => [
                        'id' => 'id_key_value'
                    ],
                    'filters' => null,
                ],
            ]
        ]

    ];

    /**
     * @param string $table_class
     * @param bool $fst_row_is_thead
     * @param array $head_cols
     * <code>
     * // Example of $head_cols
     * [
     *      [
     *         'value' => 'headline 1',
     *         'class' => 'red'
     *      ],
     *      [
     *         'value' => 'headline 2',
     *         'class' => 'red'
     *      ],
     * ]
     * </code>
     * @return string
     */
    public function asHTML($table_class = 'table table-hover', $fst_row_is_thead = true, $head_cols = []){
        $data = $this->toStdClass();
        $rows = $data->rows;

        if(empty($data->rows)){
            return null;
        }
        $html = '<table';
        if(!empty($table_class)){
            $html .= ' class="'.$table_class.'"';
        }
        $html .= '/>';
        if($fst_row_is_thead){
            $html .= '<thead>';
        }else{
            $html .= '<tbody>';
        }

        if(!empty($head_cols)){
            $max_columns = count($data->rows[0]->columns);
            array_splice($head_cols, $max_columns);
            $head_row = new \stdClass();
            $head_row->columns = $head_cols ;
            $data->rows = array_merge([$head_row], $data->rows);
        }
        foreach ($data->rows as $row => $cols){
            $html .= '<tr>';
            foreach ($cols->columns as $col) {
                $col = (array)$col;
                $html .= $fst_row_is_thead && $row == 0 ? '<th' : '<td';
                $classes = [];
                if(!empty($col['var_name'])){
                    $classes[] = $col['var_name'];
                }
                if(!empty($col['class'])){
                    $classes[] = $col['class'];
                }
                if(!empty($classes)){
                    $html .= ' class="'.implode(" ", $classes).'"';
                }
                $html .= '>';
                if(!empty($col['datatype'])){
                    if($col['datatype'] == 'string'){
                        $html .= $col['value_string'];
                    }elseif($col['datatype'] == 'integer'){
                        $html .= $col['value_integer'];
                    }elseif($col['datatype'] == 'float'){
                        $html .= $col['value_float'];
                    }elseif($col['datatype'] == 'datetime'){
                        $html .= $col['value_datetime'];
                    }
                }else{
                    $html .= $col['value'];
                }

                $html .= $fst_row_is_thead && $row == 1 ? '</th>' : '</td>';
            }
            $html .= '</tr>';
            if($fst_row_is_thead && $row == 1){
                $html .= '</thead><tbody>';
            }
        }
        $html .= '</tbody>';
        $html .= '</table>';
        return $html;
    }
}

<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Table\Row;
use Pressmind\Registry;

/**
 * Class Table
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $language
 * @property string $var_name
 * @property Row[] $rows
 */
class Table extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_tables',
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
                    'class' => '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Table\\Row',
                    'related_id' => 'id_table',
                    'on_save_related_properties' => [
                        'id' => 'id_table'
                    ],
                    'filters' => null,
                ],
            ]
        ]
    ];

    /**
     * @return array
     */
    public function get(){
        $db = Registry::getInstance()->get('db');
        $sql = 'select r.sort as row,
                   c.sort as col,
                   colspan,
                   style,
                   width,
                   height,
                   text
            from pmt2core_media_object_table_rows r
            left join pmt2core_media_object_table_row_columns c on (r.id = c.id_table_row)
            where r.id_table = ? order by r.sort asc, c.sort asc';
        $values = [$this->id];
        $result = $db->fetchAll($sql, $values);
        $table = [];
        foreach($result as $v){
            $table[$v->row][$v->col] = $v;
        }
        return $table;
    }

    /**
     * @param string $table_class
     * @param bool $fst_row_is_thead
     * @param false $use_width
     * @param false $use_height
     * @return string
     */
    public function asHTML($table_class = 'table table-hover', $fst_row_is_thead = true, $use_width = false, $use_height = false){
        $rows = $this->get();
        if(empty($rows)){
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
        foreach ($rows as $row => $cols){
                $html .= '<tr>';
            foreach ($cols as $col) {
                $html .= $fst_row_is_thead && $row == 1 ? '<th' : '<td';
                if(!empty($col->colspan) && $col->colspan != 1){
                    $html .= ' colspan="'.$col->colspan.'"';
                }
                $styles = [];
                if($use_width){
                    $styles[] = 'width:'.$col->width.'px';
                }
                if($use_height) {
                    $styles[] = 'height:' . $col->height . 'px';
                }
                if(!empty($styles)){
                    $html .= ' style="'.implode(';', $styles);
                }
                $html .= '>';
                $html .= $col->text;
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

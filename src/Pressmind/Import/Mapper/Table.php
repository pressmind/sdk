<?php


namespace Pressmind\Import\Mapper;


use Exception;
use Pressmind\ORM\Object\MediaObject\DataType\Table\Row;
use stdClass;

class Table implements MapperInterface
{
    /**
     * @param int $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param stdClass $pObject
     * @return array
     * @throws Exception
     */
    public function map($pIdMediaObject,$pLanguage, $pVarName, $pObject)
    {
        if(!is_null($pObject)) {
            $mapped_object = new stdClass();
            $mapped_object->id_media_object = $pIdMediaObject;
            $mapped_object->section_name = '';
            $mapped_object->language = $pLanguage;
            $mapped_object->var_name = $pVarName;
            $mapped_object->rows = [];
            $table_rows = [];
            $row_i = 0;
            if(isset($pObject->table) && is_array($pObject->table)) {
                foreach ($pObject->table as $row) {
                    $row_i++;
                    $table_row = new Row();
                    $table_row->sort = $row_i;
                    $table_row_columns = [];
                    $col_i = 0;
                    foreach ($row->cols as $col) {
                        $col_i++;
                        $table_row_column = new Row\Column();
                        $table_row_column->colspan = isset($col->colspan) ? $col->colspan : null;
                        $table_row_column->style = isset($col->id_style) ? $col->id_style : null;
                        $table_row_column->height = isset($col->height) ? $col->height : null;
                        $table_row_column->width = isset($col->width) ? $col->width : null;
                        $table_row_column->text = isset($col->text) ? $col->text : null;
                        $table_row_column->sort = $col_i;
                        $table_row_columns[] = $table_row_column->toStdClass();
                    }
                    $table_row->columns = $table_row_columns;
                    $table_rows[] = $table_row->toStdClass();
                    $mapped_object->rows = $table_rows;
                }
            }
            return [$mapped_object];
        } else {
            return [];
        }
    }
}

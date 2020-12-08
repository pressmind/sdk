<?php

namespace Pressmind\ORM\Object;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Search
 * @property integer $id
 * @property string $keyword
 * @property string $url
 * @property \DateTime $created_date
 * @property string $created_by
 * @property integer $items_found
 */
class Search extends AbstractObject
{
    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Search',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_searches',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 22,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'keyword' =>
                    array(
                        'title' => 'Keyword',
                        'name' => 'keyword',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'url' =>
                    array(
                        'title' => 'Url',
                        'name' => 'url',
                        'type' => 'string',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'created_date' =>
                    array(
                        'title' => 'Created_date',
                        'name' => 'created_date',
                        'type' => 'datetime',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'created_by' =>
                    array(
                        'title' => 'Created_by',
                        'name' => 'created_by',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'items_found' =>
                    array(
                        'title' => 'Items_found',
                        'name' => 'items_found',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
            ),
    );
}

<?php

namespace Pressmind\ORM\Object;

/**
 * Class AttachmentToMediaObject
 * Verknüpfungstabelle zwischen Attachments und MediaObjects (n:m Beziehung).
 * Speichert in welchem Feld/Section/Sprache ein Attachment verwendet wird.
 *
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property string $id_attachment
 * @property integer $id_media_object
 * @property string $var_name
 * @property string $section_name
 * @property string $language
 */
class AttachmentToMediaObject extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_attachment_to_media_object',
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
            'id_attachment' => [
                'title' => 'id_attachment',
                'name' => 'id_attachment',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 64,
                    ]
                ],
                'index' => [
                    'id_attachment' => 'index'
                ]
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
            'var_name' => [
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
            'section_name' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
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
            ]
        ]
    ];

    /**
     * Deletes all relations for a specific media object, var_name, section and language
     * @param int $idMediaObject
     * @param string $varName
     * @param string $sectionName
     * @param string $language
     * @return void
     */
    public static function deleteByMediaObjectField($idMediaObject, $varName, $sectionName, $language)
    {
        $db = \Pressmind\Registry::getInstance()->get('db');
        $db->execute(
            'DELETE FROM pmt2core_attachment_to_media_object WHERE id_media_object = ? AND var_name = ? AND section_name = ? AND language = ?',
            [$idMediaObject, $varName, $sectionName, $language]
        );
    }

    /**
     * Deletes all relations for a specific media object
     * @param int $idMediaObject
     * @return void
     */
    public static function deleteByMediaObject($idMediaObject)
    {
        $db = \Pressmind\Registry::getInstance()->get('db');
        $db->delete(
            'pmt2core_attachment_to_media_object',
            ['id_media_object = ?', $idMediaObject]
        );
    }
}

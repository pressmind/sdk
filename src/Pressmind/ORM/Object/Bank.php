<?php
namespace Pressmind\ORM\Object;

/**
 * Class Bank
 * @property integer $bankleitzahl
 * @property string $merkmal
 * @property string $bezeichnung
 * @property string $plz
 * @property string $ort
 * @property string $kurzbezeichnung
 * @property string $pan
 * @property string $bic
 * @property string $pruefziffermethode
 * @property string $datensatznummer
 * @property string $nachfolge_bankleitzahl
 */
class Bank extends AbstractObject
{
    protected $_definitions = array (
  'class' =>
  array (
    'name' => 'Bank',
  ),
  'database' =>
  array (
    'table_name' => 'pmt2core_banks',
    'primary_key' => 'bankleitzahl',
  ),
  'properties' =>
  array (
    'bankleitzahl' =>
    array (
      'title' => 'Bankleitzahl',
      'name' => 'bankleitzahl',
      'type' => 'integer',
      'required' => true,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 22,
        ),
      ),
      'filters' => NULL,
    ),
    'merkmal' =>
    array (
      'title' => 'Merkmal',
      'name' => 'merkmal',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'bezeichnung' =>
    array (
      'title' => 'Bezeichnung',
      'name' => 'bezeichnung',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'plz' =>
    array (
      'title' => 'Plz',
      'name' => 'plz',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'ort' =>
    array (
      'title' => 'Ort',
      'name' => 'ort',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'kurzbezeichnung' =>
    array (
      'title' => 'Kurzbezeichnung',
      'name' => 'kurzbezeichnung',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'pan' =>
    array (
      'title' => 'Pan',
      'name' => 'pan',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'bic' =>
    array (
      'title' => 'Bic',
      'name' => 'bic',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'pruefziffermethode' =>
    array (
      'title' => 'Pruefziffermethode',
      'name' => 'pruefziffermethode',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'datensatznummer' =>
    array (
      'title' => 'Datensatznummer',
      'name' => 'datensatznummer',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
    'nachfolge_bankleitzahl' =>
    array (
      'title' => 'Nachfolge_bankleitzahl',
      'name' => 'nachfolge_bankleitzahl',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 255,
        ),
      ),
      'filters' => NULL,
    ),
  ),
);}

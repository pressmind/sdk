<?php


namespace Pressmind\ValueObject\MediaObject\Result;


/**
 * Class GetPrettyUrls
 * @package Pressmind\ValueObject\MediaObject\Result
 * @property integer $id
 * @property integer $id_media_object
 * @property integer $id_object_type
 * @property integer $route
 * @property string $language
 * @property boolean $is_default
 */
class GetPrettyUrls
{
    public $id;
    public $id_media_object;
    public $id_object_type;
    public $route;
    public $language;
    public $is_default;
}

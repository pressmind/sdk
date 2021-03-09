<?php


namespace Pressmind\REST\Controller;


use Pressmind\ORM\Object\MediaObject\DataType\Picture;

class Image extends AbstractController
{
    protected $orm_class_name = Picture::class;
}

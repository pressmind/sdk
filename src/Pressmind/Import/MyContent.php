<?php


namespace Pressmind\Import;


class MyContent extends AbstractImport implements ImportInterface
{

    private $_data;

    public function __construct($data)
    {
        $this->_data = $data;
    }

    public function import()
    {
        foreach ($this->_data as $my_contents_to_media_object) {
            $my_content = new \Pressmind\ORM\Object\MediaObject\MyContent();
            $my_content->fromImport($my_contents_to_media_object);
            $my_content->create();
        }
    }
}

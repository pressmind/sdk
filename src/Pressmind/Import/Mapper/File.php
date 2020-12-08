<?php


namespace Pressmind\Import\Mapper;


use stdClass;

class File implements MapperInterface
{
    /**
     * @param int $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param stdClass $pObject
     * @return array
     */
    public function map($pIdMediaObject,$pLanguage, $pVarName, $pObject)
    {
        $result = [];
        if(is_array($pObject)) {
            foreach ($pObject as $file) {
                $mapped_object = new stdClass();
                $mapped_object->id_media_object = $pIdMediaObject;
                $mapped_object->section_name = '';
                $mapped_object->language = $pLanguage;
                $mapped_object->var_name = $pVarName;
                $mapped_object->id_file = $file->id_file;
                $mapped_object->file_name = $file->filename;
                $mapped_object->file_size = $file->filesize;
                $mapped_object->description = $file->description;
                $mapped_object->mime_type = null;
                $mapped_object->tmp_url = $file->download;
                $mapped_object->download_successful = false;
                $result[] = $mapped_object;
            }
        }
        return $result;
    }
}

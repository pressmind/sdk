<?php


namespace Pressmind\Import\Mapper;


use Pressmind\HelperFunctions;
use stdClass;

class Picture implements MapperInterface
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
            foreach ($pObject as $object) {
                $mapped_object = new stdClass();
                $mapped_object->sections = [];
                $mapped_object->id_picture = $object->id_media_object;
                $mapped_object->id_media_object = $pIdMediaObject;
                $mapped_object->section_name = '';
                $mapped_object->language = $pLanguage;
                $mapped_object->var_name = $pVarName;
                $mapped_object->caption = $object->caption;
                $mapped_object->title = $object->title;
                $mapped_object->alt = $object->alt;
                $mapped_object->copyright = $object->copyright;
                $mapped_object->tmp_url = $object->image->links->web;
                if (!empty($object->image->filename)) {
                    $path_info = pathinfo($object->image->filename);
                    $mapped_object->file_name = $pIdMediaObject . '_' . $object->id_media_object . '.' . $path_info['extension'];
                }
                $mapped_object->width = $object->image->width;
                $mapped_object->height = $object->image->height;
                $mapped_object->file_size = $object->image->filesize;
                if (isset($object->picture_sections) && is_a($object->picture_sections, 'stdClass')) {
                    foreach ($object->picture_sections as $picture_section_id => $picture_section) {
                        $mapped_section_object = new stdClass();
                        $mapped_section_object->id_media_object = $pIdMediaObject;
                        $mapped_section_object->section_name = HelperFunctions::human_to_machine($picture_section->name);
                        //$mapped_section_object->uri = isset($object->uri) ? $object->uri : '';
                        $mapped_section_object->tmp_url = $picture_section->link;
                        $mapped_section_object->file_name = $pIdMediaObject . '_' . $object->id_media_object . '_' . HelperFunctions::human_to_machine($picture_section->name) . '.' . $path_info['extension'];
                        $mapped_section_object->width = round($picture_section->img_width);
                        $mapped_section_object->height = round($picture_section->img_height);
                        $mapped_section_object->file_size = null;
                        $mapped_object->sections[] = $mapped_section_object;
                    }
                }
                $result[] = $mapped_object;
            }
        }
        return $result;
    }
}

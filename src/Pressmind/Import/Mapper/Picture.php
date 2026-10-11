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
            $sort = 0;
            foreach ($pObject as $object) {
                if(empty($object->image->filename)){
                    continue;
                }
                if($object->image->visibility == 10){
                    continue;
                }
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
                $mapped_object->uri = !empty($object->uri) ? $object->uri : null;
                $mapped_object->copyright = $object->copyright;
                $mapped_object->is_ai = $object->image->is_ai ?? null;
                $mapped_object->ai_disclosure = $object->image->ai_disclosure ?? null;
                $mapped_object->disabled = isset($object->disabled) ? $object->disabled : false;
                $mapped_object->tmp_url = $object->image->links->web->url;
                $mapped_object->mime_type = $object->image->links->web->mime_type;
                $mapped_object->sort = $sort;
                $mapped_object->file_name = $pIdMediaObject . '_' . $object->id_media_object . '.'. HelperFunctions::getExtensionFromMimeType($object->image->links->web->mime_type);
                $mapped_object->width = $object->image->width;
                $mapped_object->height = $object->image->height;
                $mapped_object->file_size = $object->image->filesize;
                $video = $object->image->video ?? null;
                $video_url = (is_object($video) && !empty($video->url) && is_string($video->url)) ? $video->url : null;
                $mapped_object->video_tmp_url = $video_url;
                $mapped_object->video_file_name = $video_url !== null ? $pIdMediaObject . '_' . $object->id_media_object . '_' . md5($video_url) . '.mp4' : null;
                $mapped_object->video_mime_type = $video_url !== null ? ($video->mime_type ?? null) : null;
                $mapped_object->video_width = $video_url !== null ? ($video->width ?? null) : null;
                $mapped_object->video_height = $video_url !== null ? ($video->height ?? null) : null;
                $mapped_object->video_duration = $video_url !== null ? ($video->duration ?? null) : null;
                $mapped_object->video_file_size = $video_url !== null ? ($video->filesize ?? null) : null;
                $mapped_object->video_download_successful = $video_url !== null ? false : null;
                if (isset($object->picture_sections) && is_a($object->picture_sections, 'stdClass')) {
                    foreach ($object->picture_sections as $picture_section_id => $picture_section) {
                        if(empty($picture_section->name)){
                            continue;
                        }
                        $mapped_section_object = new stdClass();
                        $mapped_section_object->id_media_object = $pIdMediaObject;
                        $mapped_section_object->section_name = HelperFunctions::human_to_machine($picture_section->name);
                        $mapped_section_object->tmp_url = $picture_section->link;
                        $mapped_section_object->file_name = $pIdMediaObject . '_' . $object->id_media_object . '_' . HelperFunctions::human_to_machine($picture_section->name) .'_'.md5($picture_section->link). '.jpg';
                        $mapped_section_object->width = $picture_section->img_width;
                        $mapped_section_object->height = $picture_section->img_height;
                        $mapped_section_object->img_x = $picture_section->img_x;
                        $mapped_section_object->img_y = $picture_section->img_y;
                        $mapped_section_object->file_size = null;
                        $mapped_section_object->mime_type = "image/jpeg";
                        $mapped_object->sections[] = $mapped_section_object;
                    }
                }
                $result[] = $mapped_object;
                $sort++;
            }
        }
        return $result;
    }
}

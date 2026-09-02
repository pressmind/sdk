<?php

namespace Pressmind\Import\Mapper;

use Pressmind\Import\IconNormalizer;
use stdClass;

class Icon implements MapperInterface
{
    /**
     * @param int $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param stdClass|array|null $pObject
     * @return array
     */
    public function map($pIdMediaObject, $pLanguage, $pVarName, $pObject)
    {
        if ($pObject === null) {
            return [];
        }

        $icon = IconNormalizer::normalize($pObject);
        if ($icon === null) {
            return [];
        }

        $mappedObject = new stdClass();
        $mappedObject->id_media_object = $pIdMediaObject;
        $mappedObject->section_name = '';
        $mappedObject->language = $pLanguage;
        $mappedObject->var_name = $pVarName;
        $mappedObject->id_icon = $icon['id'];
        $mappedObject->name = $icon['name'];
        $mappedObject->slug = $icon['slug'];
        $mappedObject->url = $icon['url'];
        $mappedObject->mime = $icon['mime'];
        $mappedObject->style = $icon['style'];
        $mappedObject->variants = $icon['variants'];

        return [$mappedObject];
    }
}

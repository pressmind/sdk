<?php


namespace Pressmind\REST\Controller;


class MediaObject extends AbstractController
{
    public function getByRoute($params)
    {
        $readRelations = (isset($params['readRelations']) && boolval($params['readRelations']) == true) ? $params['readRelations'] : false;
        $routes = \Pressmind\ORM\Object\MediaObject::getByPrettyUrl($params['route'], $params['id_object_type'], isset($params['language']) ? $params['language'] : 'de', $params['visibility']);
        foreach ($routes as $route) {
            $media_object = new \Pressmind\ORM\Object\MediaObject($route->id, $readRelations);
            if(isset($params['apiTemplate']) && !empty($params['apiTemplate'])) {
                return $media_object->renderApiOutputTemplate($params['apiTemplate']);
            }
            return $media_object;
        }
    }

    public function getByCode($params)
    {
        return \Pressmind\ORM\Object\MediaObject::getByCode($params['code']);
    }
}

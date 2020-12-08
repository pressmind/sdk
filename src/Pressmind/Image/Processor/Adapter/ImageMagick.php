<?php


namespace Pressmind\Image\Processor\Adapter;


use Imagick;
use ImagickException;
use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class ImageMagick implements AdapterInterface
{

    private $_image;

    /**
     * @param Config $config
     * @param File $file
     * @param string $derivative_name
     * @return File
     * @throws ImagickException
     */
    public function process($config, $file, $derivative_name)
    {
        $this->_image = new Imagick();
        $this->_image->readImageBlob($file->content);
        if($config->crop == true) {
            $this->_image->cropThumbnailImage($config->max_width,$config->max_height);
        } else {
            $this->_image->thumbnailImage($config->max_width, $config->max_height, $config->preserve_aspect_ratio);
        }
        $path_info = pathinfo($file->name);
        $new_name = $path_info['filename'] . '_' . $derivative_name . '.' . $path_info['extension'];
        $derivative_file = new File($file->getBucket());
        $derivative_file->name = $new_name;
        $derivative_file->content = $this->_image->getImageBlob();
        return $derivative_file;
    }
}

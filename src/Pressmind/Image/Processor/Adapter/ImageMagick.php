<?php


namespace Pressmind\Image\Processor\Adapter;


use Imagick;
use ImagickException;
use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\Log\Writer;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class ImageMagick implements AdapterInterface
{

    /**
     * @param Config $config
     * @param File $file
     * @param string $derivativeName
     * @return File
     * @throws ImagickException
     */
    public function process($config, $file, $derivativeName)
    {
        $path_info = pathinfo($file->name);
        //$new_name = $path_info['filename'] . '_' . $derivativeName . '.' . $path_info['extension'];
        $new_name = $path_info['filename'] . '_' . $derivativeName . '.jpg';
        $derivative_file = new File($file->getBucket());
        $derivative_file->name = $new_name;
        $derivative_file->mimetype = 'image/jpeg';
        if($derivative_file->exists()) {
            Writer::write('Derivative ' . $derivative_file->name . ' already exists. Skipping ...', WRITER::OUTPUT_SCREEN, 'image_processor', WRITER::TYPE_INFO);
            $derivative_file->read();
            return $derivative_file;
        }
        Writer::write('Generating derivative ' . $derivativeName . ' for file ' . $file->name, WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
        $image = new Imagick();
        $image->readImageBlob($file->content);
        if($config->crop == true) {
            $image->cropThumbnailImage($config->max_width,$config->max_height);
        } else {
            $image->thumbnailImage($config->max_width, $config->max_height, $config->preserve_aspect_ratio);
        }

        if($image->getImageFormat() != 'jpg'){
            $image->setCompressionQuality(85);
            $image->setImageFormat('jpg');
        }

        $derivative_file->content = $image->getImageBlob();
        Writer::write('Derivative ' . $derivative_file->name . ' for file ' . $file->name . ' generated', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
        return $derivative_file;
    }
}

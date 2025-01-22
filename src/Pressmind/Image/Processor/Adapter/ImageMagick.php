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
        if(is_a($file, File::class) === false) {
            throw new ImagickException('Error: $file must be of type ' . File::class);
        }
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

    /**
     * @TODO improve this: it does actually not work with some graphics (like maps)...
     * Checks an image for visual corruption
     * @param File $file
     * @return bool
     * @throws ImagickException
     */
   public function isImageCorrupted($file) {
        if(is_a($file, File::class) === false) {
            throw new ImagickException('Error: $file must be of type ' . File::class);
        }
        if (!$file->exists()) {
            throw new \Exception('File not found: ' . $file->name);
        }
        try {
            $image = new Imagick();
            $file->read();
            $image->readImageBlob($file->content);
            $histogram = $image->getImageHistogram();
            $totalPixels = $image->getImageWidth() * $image->getImageHeight();
            $threshold = 0.7;
            foreach ($histogram as $pixel) {
                $count = $pixel->getColorCount();
                if ($count / $totalPixels > $threshold) {
                    Writer::write('Image is corrupted: '.$file->name, WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_ERROR);
                    return true;
                }
            }
        } catch (\Exception $e) {
            Writer::write('ImageMagick:isImageCorrupted() Exception:'.$e->getMessage(), WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_ERROR);
            return true;
        }
        return false;
    }

}

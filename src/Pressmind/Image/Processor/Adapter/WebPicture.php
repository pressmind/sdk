<?php
namespace Pressmind\Image\Processor\Adapter;


use Exception;
use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\Log\Writer;
use Pressmind\Storage\File;

class WebPicture implements AdapterInterface
{
    /**
     * @param Config $config
     * @param File $file
     * @param string $derivativeName
     * @return File|null
     * @throws Exception
     */
    public function process($config, $file, $derivativeName)
    {
        if(true === $config->webp_create) {
            $webp_file = new File($file->getBucket());
            $path_info = pathinfo($file->name);
            $webp_file->name = $path_info['filename'] . '.webp';
            Writer::write('Generating WebP-Version ' . $webp_file->name . ' for file ' . $file->name, WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
            if(!$webp_file->exists()) {
                $image = imagecreatefromstring($file->content);
                ob_start();
                imagewebp($image, null, $config->webp_quality);
                $raw_content = ob_get_contents();
                ob_end_clean();
                $webp_file->content = $raw_content;
                $webp_file->save();
                Writer::write('WebP-Version ' . $webp_file->name . ' for file ' . $file->name . ' generated', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
            } else {
                Writer::write('WebP-Version ' . $webp_file->name . ' allready exists. Skipping ...', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
            }
            return $webp_file;
        }
        return null;
    }

    /**
     * @TODO
     * @param File $file
     * @return mixed
     */
    public function isImageCorrupted($file){
        return false;
    }
}

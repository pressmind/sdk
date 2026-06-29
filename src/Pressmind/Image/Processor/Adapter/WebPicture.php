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
            if($webp_file->exists()) {
                try {
                    if ($webp_file->filesize() > 0) {
                        $webp_file->read();
                        $existing_image = @imagecreatefromstring($webp_file->content);
                        if ($existing_image !== false) {
                            imagedestroy($existing_image);
                            Writer::write('WebP-Version ' . $webp_file->name . ' allready exists. Skipping ...', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
                            return $webp_file;
                        }
                    }
                    Writer::write('Existing WebP-Version ' . $webp_file->name . ' is empty or corrupt. Regenerating ...', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_WARNING);
                } catch (Exception $e) {
                    Writer::write('Existing WebP-Version ' . $webp_file->name . ' could not be checked: ' . $e->getMessage() . '. Regenerating ...', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_WARNING);
                }
            }

            $image = @imagecreatefromstring($file->content);
            if ($image === false) {
                throw new Exception('Could not create WebP source image from file ' . $file->name);
            }
            if (!imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            try {
                $success = imagewebp($image, null, $config->webp_quality ?? -1);
                $raw_content = ob_get_contents();
            } finally {
                ob_end_clean();
                imagedestroy($image);
            }

            if ($success !== true || $raw_content === false || $raw_content === '') {
                throw new Exception('Could not encode WebP image for file ' . $file->name);
            }

            $webp_file->content = $raw_content;
            $webp_file->save();
            Writer::write('WebP-Version ' . $webp_file->name . ' for file ' . $file->name . ' generated', WRITER::OUTPUT_FILE, 'image_processor', WRITER::TYPE_INFO);
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

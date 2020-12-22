<?php
namespace Pressmind\Image\Processor\Adapter;


use Exception;
use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;

class WebPicture implements AdapterInterface
{
    /**
     * @param Config $config
     * @param File $file
     * @param string $derivativeName
     * @return mixed|void
     * @throws Exception
     */
    public function process($config, $file, $derivativeName)
    {
        if(true === $config->webp_create) {
            $image = imagecreatefromstring($file->content);
            ob_start();
            imagewebp($image, null, $config->webp_quality);
            $raw_content = ob_get_contents();
            ob_end_clean();
            $webp_file = new File($file->getBucket());
            $webp_file->content = $raw_content;
            $path_info = pathinfo($file->name);
            $webp_file->name = $path_info['filename'] . '.webp';
            $webp_file->save();
        }
    }
}

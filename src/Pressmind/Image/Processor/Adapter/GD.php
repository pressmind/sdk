<?php


namespace Pressmind\Image\Processor\Adapter;


use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;
use \Exception;

class GD implements AdapterInterface
{

    /**
     * @TODO: this must deliver a jpg, see ImageMagick.php!
     * @param Config $config
     * @param File $file
     * @param string $derivativeName
     * @return false|File
     * @throws Exception
     */
    public function process($config, $file, $derivativeName) {

        $path_info = pathinfo($file->name);
        $new_name = $path_info['filename'] . '_' . $derivativeName . '.' . $path_info['extension'];
        $derivative_file = new File($file->getBucket());
        $derivative_file->name = $new_name;
        if($derivative_file->exists()) {
            $derivative_file->read();
            return $derivative_file;
        }

        $info = getimagesize($file->content);

        list($width_old, $height_old) = $info;

        if ($config->preserve_aspect_ratio) {
            if ($config->max_width == 0) $factor = $config->max_height/$height_old;
            elseif ($config->max_height == 0) $factor = $config->max_width/$width_old;
            else $factor = min ( $config->max_width / $width_old, $config->max_height / $height_old);

            $final_width = round ($width_old * $factor);
            $final_height = round ($height_old * $factor);

        }
        else {
            $final_width = ( $config->max_width <= 0 ) ? $width_old : $config->max_width;
            $final_height = ( $config->max_height <= 0 ) ? $height_old : $config->max_height;
        }

        switch ( $info[2] ) {
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($file->content);
                break;
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file->content);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file->content);
                break;
            default:
                return false;
        }

        $image_resized = imagecreatetruecolor( $final_width, $final_height );

        if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) ) {
            $trnprt_indx = imagecolortransparent($image);
            if ($trnprt_indx >= 0) {
                $trnprt_color    = imagecolorsforindex($image, $trnprt_indx);
                $trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                imagefill($image_resized, 0, 0, $trnprt_indx);
                imagecolortransparent($image_resized, $trnprt_indx);


            }
            elseif ($info[2] == IMAGETYPE_PNG) {
                imagealphablending($image_resized, false);
                $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
                imagefill($image_resized, 0, 0, $color);
                imagesavealpha($image_resized, true);
            }
        }

        imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);

        ob_start();
        switch ( $info[2] ) {
            case IMAGETYPE_GIF:
                imagegif($image_resized);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($image_resized);
                break;
            case IMAGETYPE_PNG:
                imagepng($image_resized);
                break;
            default:
                return false;
        }
        $derivative_file->content = ob_get_clean();

        return $derivative_file;
    }

}

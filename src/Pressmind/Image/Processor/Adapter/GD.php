<?php


namespace Pressmind\Image\Processor\Adapter;


use Pressmind\Image\Processor\AdapterInterface;
use Pressmind\Image\Processor\Config;

class GD implements AdapterInterface
{

    /**
     * @param Config $config
     * @param string $file
     * @param string $derivative_name
     * @return string
     */
    public function process($config, $file, $derivative_name) {

        $path_info = pathinfo($file);
        $path = $path_info['dirname'];
        $new_name = $path_info['filename'] . '_' . $derivative_name . '.' . $path_info['extension'];

        if ( $config->max_width <= 0 && $config->max_width <= 0 ) {
            return false;
        }

        $info = getimagesize($file);

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
                $image = imagecreatefromgif($file);
                break;
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file);
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

        switch ( $info[2] ) {
            case IMAGETYPE_GIF:
                imagegif($image_resized, $path . DIRECTORY_SEPARATOR . $new_name);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($image_resized, $path . DIRECTORY_SEPARATOR . $new_name);
                break;
            case IMAGETYPE_PNG:
                imagepng($image_resized, $path . DIRECTORY_SEPARATOR . $new_name);
                break;
            default:
                return false;
        }

        return $path . DIRECTORY_SEPARATOR . $new_name;
    }

}

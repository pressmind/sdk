<?php


namespace Pressmind\Image;


use Exception;

class Download
{

    /**
     * @var string
     */
    private $_file_type;


    public function __construct($file_type = 'image')
    {
        $this->_file_type = $file_type;
    }

    /**
     * @param string $url
     * @param string $targetPath
     * @param string $targetName
     * @return string
     * @throws Exception
     */
    public function download($url, $targetPath, $targetName)
    {
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $raw = curl_exec($ch);
        curl_close ($ch);
        if($raw === false) {
            throw new Exception('CURL Timeout or other error: ' . curl_error($ch));
        } else {
            if (!empty($targetName) && !is_dir($targetPath . DIRECTORY_SEPARATOR . $targetName) && file_exists($targetPath . DIRECTORY_SEPARATOR . $targetName)) {
                unlink($targetPath . DIRECTORY_SEPARATOR . $targetName);
            }
            $fp = fopen($targetPath . DIRECTORY_SEPARATOR . $targetName, 'w');
            if($fp === false) {
                throw new Exception('failed to open ' . $targetPath . DIRECTORY_SEPARATOR . $targetName . ' for writing');
            }
            fwrite($fp, $raw);
            fclose($fp);
            if ($this->_file_type !== 'image') {
                $new_name = $targetName;
            } else {
                $path_info = pathinfo($targetPath . DIRECTORY_SEPARATOR . $targetName);
                $image_info = getimagesize($targetPath . DIRECTORY_SEPARATOR . $targetName);
                if(!is_array($image_info)) {
                    unlink($targetPath . DIRECTORY_SEPARATOR . $targetName);
                    throw new Exception('getimagesize(' . $targetPath . DIRECTORY_SEPARATOR . $targetName . ') failed');
                }
                $extension = image_type_to_extension($image_info[2]);
                $new_name = $path_info['filename'] . $extension;
            }
            rename($targetPath . DIRECTORY_SEPARATOR . $targetName, $targetPath . DIRECTORY_SEPARATOR . $new_name);
            return $new_name;
        }
    }
}

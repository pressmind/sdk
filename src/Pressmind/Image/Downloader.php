<?php


namespace Pressmind\Image;


use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class Downloader
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
     * @return File
     * @throws Exception
     */
    public function download($url, $targetName)
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['image_handling']['storage']['bucket']);
        $file = new File($bucket);
        $file->name = $targetName;
        if(!$file->exists()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw === false || empty($raw)) {
                throw new Exception('CURL Timeout or other error: ' . curl_error($ch));
            } else {
                $file->content = $raw;
            }
        } else {
            $file->read();
        }
        return $file;
    }
}

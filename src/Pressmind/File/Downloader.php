<?php


namespace Pressmind\File;


use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class Downloader
{
    /**
     * @param string $url
     * @param string $targetName
     * @return File
     * @throws Exception
     */
    public function download($url, $targetName)
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['file_handling']['storage']);
        $file = new File($bucket);
        $file->name = $targetName;
        if (!$file->exists()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw === false) {
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

<?php


namespace Pressmind\Image;


use Exception;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class Downloader
{
    /**
     * @var string
     */
    private $_file_type;

    /**
     * Downloader constructor.
     * @param string $file_type
     */
    public function __construct($file_type = 'image')
    {
        $this->_file_type = $file_type;
    }

    /**
     * @param string $url
     * @param string $targetName
     * @param boolean $forceOverwrite
     * @return File
     * @throws Exception
     */
    public function download($url, $targetName, $forceOverwrite = false)
    {
        Writer::write('Download of file ' . $targetName . ' from ' . $url . ' requested', WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_INFO);
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['image_handling']['storage']);
        $file = new File($bucket);
        $file->name = $targetName;
        if($this->lockFileExists($targetName)){
           throw new Exception( Writer::write('Lock exists, download aborted', WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_INFO));
        }
        if(!$file->exists() || $forceOverwrite === true) {
            Writer::write('File ' . $file->name . ' does not exists. Downloading ...', WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_INFO);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $raw = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if($http_code == 429){
                Writer::write('CURL Error, HTTP Code 429, (to many request or api token wrong), raw: ' . $raw, WRITER::OUTPUT_SCREEN, 'image_processor', WRITER::TYPE_ERROR);
                exit;
            }else if ($raw === false || $http_code != 200) {
                throw new Exception(Writer::write('CURL HTTP Code '.$http_code.' ' . $error, WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_ERROR));
            } else if(empty($raw)) {
                throw new Exception(Writer::write('Empty response for: ' . $url, WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_ERROR));
            } else {
                $file->content = $raw;
            }
        } else {
            Writer::write('File ' . $file->name . ' already exists. Skipping ...', WRITER::OUTPUT_BOTH, 'image_processor', WRITER::TYPE_INFO);
            // @TODO this is not performant if all asset are stored on s3 (produces a download request)
            $file->read();
        }

        return $file;
    }
    

    /**
     * @param $filename
     * @param $content
     * @return boolean
     * @throws Exception
     */
    public function writeLockFile($filename, $content = null){
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['image_handling']['storage']);
        $LockFile = new \Pressmind\Storage\File($bucket);
        $LockFile->content = $content;
        $LockFile->name = $filename.'.lock';
        return $LockFile->save();
    }


    /**
     * @param $filename
     * @return bool
     */
    public function lockFileExists($filename){
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['image_handling']['storage']);
        $LockFile = new \Pressmind\Storage\File($bucket);
        $LockFile->name = $filename.'.lock';
        return $LockFile->exists();
    }

    
}

<?php


namespace Pressmind\Storage\Provider;


use DirectoryIterator;
use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Storage\AbstractProvider;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;

class Filesystem extends AbstractProvider implements ProviderInterface
{

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return bool|true
     * @throws Exception
     */
    public function save($file, $bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        if(false === file_exists($bucket->name)){
            @mkdir($bucket->name, 0755, true);
        }
        if(false === file_put_contents($bucket->name . DIRECTORY_SEPARATOR . $file->name, $file->content)) {
            throw new Exception('Failed to save file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name);
        }
        return true;
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return bool|true
     * @throws Exception
     */
    public function delete($file, $bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        if($this->fileExists($file, $bucket)) {
            if(false === unlink($bucket->name . DIRECTORY_SEPARATOR . $file->name)) {
                throw new Exception('Failed to unlink file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name);
            }
        }
        return true;
    }

    public function deleteAll($bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        $dir = rtrim($bucket->name , '/');
        if(!empty($dir) && file_exists($dir) && is_dir($dir) && substr_count($dir, '/') > 5){
            $perms = fileperms($dir);
            $owner = fileowner($dir);
            exec('rm -rf '.$dir, $o, $r);
            if($r > 0){
                throw new Exception('deletion failed: '.print_r($o, true));
            }
            mkdir($dir);
            chmod($dir, $perms);
            chown($dir, $owner);
        }else{
            throw new Exception('Path is possible to short or does not exists: ('.$dir.'), stop here for security reasons ');
        }
        return true;
    }

    public function bucketExists($bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        return is_dir($bucket->name);
    }

    public function createBucket()
    {

    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return bool
     */
    public function fileExists($file, $bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        if(empty($file->name)){
            return false;
        }
        return file_exists($bucket->name . DIRECTORY_SEPARATOR . $file->name);
    }
    /**
     * @param File $file
     * @param Bucket $bucket
     * @return File
     * @throws Exception
     */
    public function readFile($file, $bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        if($this->fileExists($file, $bucket)) {
            $content = file_get_contents($bucket->name . DIRECTORY_SEPARATOR . $file->name);
            if($content !== false) {
                $file->content = $content;
                $file->hash = md5($content);
                return $file;
            } else {
                throw new Exception('Failed to read file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name);
            }
        } else {
            throw new Exception('Failed to read file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name . '. File does not exist.');
        }
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return bool|true
     * @throws Exception
     */
    public function setFileMode($file, $bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        if($this->fileExists($file, $bucket)) {
            if(chmod ($bucket->name . DIRECTORY_SEPARATOR . $file->name, $file->mode) !== false) {
                return true;
            } else {
                throw new Exception('Failed to chmod file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name);
            }
        } else {
            throw new Exception('Failed to chmod file: ' . $bucket->name . DIRECTORY_SEPARATOR . $file->name . '. File does not exist.');
        }
    }

    /**
     * @param Bucket $bucket
     * @return File[]
     */
    public function listBucket($bucket)
    {
        $bucket->name = HelperFunctions::replaceConstantsFromConfig($bucket->name);
        $files = [];
        foreach (new DirectoryIterator($bucket->name) as $item) {
            if($item->isFile()) {
                $file = new File($bucket);
                $file->name = $item->getFilename();
                $files[] = $file;
            }
        }
        return $files;
    }
}

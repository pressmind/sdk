<?php


namespace Pressmind\Storage;


use Exception;

interface ProviderInterface
{
    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function save($file, $bucket);

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function delete($file, $bucket);

    /**
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function deleteAll($bucket);

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return boolean
     */
    public function fileExists($file, $bucket);

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return File
     * @throws Exception
     */
    public function readFile($file, $bucket);

    /**
     * @param $file
     * @param $bucket
     * @return int
     * @throws Exception
     */
    public function filesize($file, $bucket);

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function setFileMode($file, $bucket);

    /**
     * @param Bucket $bucket
     * @return File[]
     * @throws Exception
     */
    public function listBucket($bucket);
}

<?php


namespace Pressmind\Storage;


class AbstractProvider
{
    /**
     * @var File
     */
    protected $_file;

    /**
     * @var Bucket
     */
    protected $_bucket;

    /**
     * AbstractProvider constructor.
     * @param null|Bucket $bucket
     * @param null|File $file
     */
    public function __construct($bucket = null, $file = null)
    {
        $this->setBucket($bucket);
        $this->setFile($file);
    }

    /**
     * @param Bucket $bucket
     */
    public function setBucket($bucket)
    {
        $this->_bucket = $bucket;
    }

    /**
     * @return Bucket
     */
    public function getBucket(): Bucket
    {
        return $this->_bucket;
    }

    /**
     * @param File $file
     */
    public function setFile($file): void
    {
        $this->_file = $file;
    }

    /**
     * @return File
     */
    public function getFile(): File
    {
        return $this->_file;
    }
}

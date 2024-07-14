<?php


namespace Pressmind\Storage;


use Exception;
use finfo;

class File
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $tags;

    /**
     * @var string
     */
    public $hash;

    /**
     * @var string
     */
    public $mimetype;

    /**
     * @var string
     */
    public $content;

    /**
     * @var integer
     */
    public $mode = 644;

    /**
     * @var Bucket
     */
    private $_bucket;

    /**
     * File constructor.
     * @param Bucket $bucket
     */
    public function __construct($bucket)
    {
        $this->_bucket = $bucket;
    }

    /**
     * @param string $tag
     */
    public function addTag($tag)
    {
        $this->tags[] = $tag;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return true
     * @throws Exception
     */
    public function save()
    {
        $this->hash = md5($this->content);
        return $this->_bucket->addFile($this);
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return $this->_bucket->fileExists($this);
    }

    /**
     * @return File
     * @throws Exception
     */
    public function read()
    {
        return $this->_bucket->readFile($this);
    }

    /**
     * @return int
     * @throws Exception
     */
    public function filesize()
    {
        return $this->_bucket->filesize($this);
    }

    /**
     * @param integer $mode
     * @return true
     * @throws Exception
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this->_bucket->setFileMode($this);
    }

    /**
     * @return true
     * @throws Exception
     */
    public function delete()
    {
        return $this->_bucket->removeFile($this);
    }

    /**
     * @return string|null
     */
    public function getMimetype()
    {
        $mime_type = empty($this->mimetype) ? null : $this->mimetype;
        if(is_null($mime_type) && !empty($this->content)) {
            $file_info = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $file_info->buffer($this->content);
            $this->mimetype = $mime_type;
        }
        return $mime_type;
    }

    /**
     * @return Bucket
     */
    public function getBucket()
    {
        return $this->_bucket;
    }
}

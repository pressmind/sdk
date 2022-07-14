<?php


namespace Pressmind\Storage;


use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Provider\Factory;

class Bucket
{
    /**
     * @var string
     */
    public $name;

    private $_config;
    
    public $storage = [];

    /**
     * Bucket constructor.
     * @param array $storage
     */
    public function __construct($storage) {
        $this->name = $storage['bucket'];
        $this->_config = Registry::getInstance()->get('config');
        $this->storage = $storage;

    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function addFile($file)
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->save($file, $this);
    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function removeFile($file)
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->delete($file, $this);
    }
    /**
     * @return true
     * @throws Exception
     */
    public function removeAll()
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->deleteAll($this);
    }

    /**
     * @param $file
     * @return boolean
     */
    public function fileExists($file)
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->fileExists($file, $this);
    }

    /**
     * @param File $file
     * @return File
     * @throws Exception
     */
    public function readFile($file)
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->readFile($file, $this);
    }

    /**
     * @param File $file
     * @return true
     * @throws Exception
     */
    public function setFileMode($file)
    {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->setFileMode($file, $this);
    }


    /**
     * @return File[]
     * @throws Exception
     */
    public function listFiles() {
        $storageProvider = Factory::create($this->storage);
        return $storageProvider->listBucket($this);
    }
}

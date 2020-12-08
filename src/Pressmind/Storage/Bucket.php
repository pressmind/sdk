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

    private $_storage_provider_name = null;

    /**
     * Bucket constructor.
     * @param string $name
     */
    public function __construct($name) {
        $this->name = $name;
        $this->_config = Registry::getInstance()->get('config');
        $this->_storage_provider_name = $this->_config['file_handling']['storage']['provider'];
    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function addFile($file)
    {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->save($file, $this);
    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function removeFile($file)
    {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->delete($file, $this);
    }

    /**
     * @param $file
     * @return boolean
     */
    public function fileExists($file)
    {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->fileExists($file, $this);
    }

    /**
     * @param File $file
     * @return File
     * @throws Exception
     */
    public function readFile($file)
    {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->readFile($file, $this);
    }

    /**
     * @param File $file
     * @return true
     * @throws Exception
     */
    public function setFileMode($file)
    {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->setFileMode($file, $this);
    }


    /**
     * @return File[]
     * @throws Exception
     */
    public function listFiles() {
        /** @var ProviderInterface $storageProvider */
        $storageProvider = Factory::create($this->_storage_provider_name);
        return $storageProvider->listBucket($this);
    }
}

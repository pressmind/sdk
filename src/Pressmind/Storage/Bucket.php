<?php


namespace Pressmind\Storage;


use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Provider\Factory;
use Pressmind\Storage\ProviderInterface;
use Pressmind\Storage\PrefixListableInterface;

class Bucket
{
    /**
     * @var string
     */
    public $name;

    private $_config;

    /**
     * @var ProviderInterface|null
     */
    private $_provider = null;

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
     * Returns the storage provider instance (lazy-initialized, cached).
     * @return ProviderInterface
     */
    private function getProvider(): ProviderInterface
    {
        if ($this->_provider === null) {
            $this->_provider = Factory::create($this->storage);
        }
        return $this->_provider;
    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function addFile($file)
    {
        return $this->getProvider()->save($file, $this);
    }

    /**
     * @param $file
     * @return true
     * @throws Exception
     */
    public function removeFile($file)
    {
        return $this->getProvider()->delete($file, $this);
    }
    /**
     * @return true
     * @throws Exception
     */
    public function removeAll()
    {
        return $this->getProvider()->deleteAll($this);
    }

    /**
     * @param $file
     * @return boolean
     */
    public function fileExists($file)
    {
        return $this->getProvider()->fileExists($file, $this);
    }

    /**
     * @param File $file
     * @return File
     * @throws Exception
     */
    public function readFile($file)
    {
        return $this->getProvider()->readFile($file, $this);
    }

    /**
     * @param $file
     * @return int
     * @throws Exception
     */
    public function filesize($file)
    {
        return $this->getProvider()->filesize($file, $this);
    }

    /**
     * @param File $file
     * @return true
     * @throws Exception
     */
    public function setFileMode($file)
    {
        return $this->getProvider()->setFileMode($file, $this);
    }


    /**
     * @return File[]
     * @throws Exception
     */
    public function listFiles() {
        return $this->getProvider()->listBucket($this);
    }

    /**
     * Whether the storage provider supports listByPrefix (e.g. S3, Filesystem).
     */
    public function supportsPrefixListing(): bool
    {
        return $this->getProvider() instanceof PrefixListableInterface;
    }

    /**
     * Lists files by prefix with their sizes. One API call per prefix (e.g. per image) instead of per-file HEAD requests.
     *
     * @param string $prefix Key prefix (e.g. "image_12345_")
     * @return array<string, int> filename => size in bytes, or empty array if provider does not support prefix listing
     */
    public function listByPrefix(string $prefix): array
    {
        $provider = $this->getProvider();
        if ($provider instanceof PrefixListableInterface) {
            return $provider->listByPrefix($prefix, $this);
        }
        return [];
    }
}

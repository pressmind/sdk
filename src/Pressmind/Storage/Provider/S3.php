<?php


namespace Pressmind\Storage\Provider;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;

class S3 implements ProviderInterface
{

    private $_s3_client;

    public function __construct()
    {
        $config = Registry::getInstance()->get('config');
        $this->_s3_client = new S3Client([
            'version' => 'latest',
            'region' => 'eu-central-1',
            'credentials' => $config['file_handling']['storage']['credentials']
        ]);
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function save($file, $bucket)
    {
        file_put_contents(APPLICATION_PATH . '/s3log.txt', $file->name . "\n", FILE_APPEND);
        try {
            $result = $this->_s3_client->putObject([
                'Bucket' => $bucket->name,
                'Key' => $file->name,
                'Body' => $file->content
            ]);
        } catch (S3Exception $e) {
            file_put_contents(APPLICATION_PATH . '/s3log.txt', $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function delete($file, $bucket)
    {
        $result = $this->_s3_client->deleteObject([
            'Bucket' => $bucket->name,
            'Key' => $file->name,
        ]);
        if (!$result['DeleteMarker'])
        {
            throw new Exception('Failed to unlink file: ' . BASE_PATH . DIRECTORY_SEPARATOR . $bucket->name . DIRECTORY_SEPARATOR . $file->name);
        }
        return true;
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return boolean
     */
    public function fileExists($file, $bucket)
    {
        return $this->_s3_client->doesObjectExist($bucket->name, $file->name);
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return File
     * @throws Exception
     */
    public function readFile($file, $bucket)
    {
        $result = $this->_s3_client->getObject([
            'Bucket' => $bucket->name,
            'Key' => $file->name
        ]);
        $file->content = $result['Body'];
        return $file;
    }

    /**
     * @param File $file
     * @param Bucket $bucket
     * @return true
     * @throws Exception
     */
    public function setFileMode($file, $bucket)
    {
        // TODO: Implement setFileMode() method.
    }

    /**
     * @param Bucket $bucket
     * @return File[]
     * @throws Exception
     */
    public function listBucket($bucket)
    {
        $files = [];
        $result = $this->_s3_client->listObjects([
            'Bucket' => $bucket->name
        ]);
        foreach ($result['Contents'] as $content) {
            $file = new File($bucket);
            $file->name = $content['Key'];
            $files[] = $file;
        }
        return $files;
    }
}

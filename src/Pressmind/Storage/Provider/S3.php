<?php


namespace Pressmind\Storage\Provider;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;

class S3 implements ProviderInterface
{

    /**
     * @var S3Client
     */
    private $_s3_client;

    /**
     * @var string
     */
    private $_log_file_name = 's3_storage';

    public function __construct($storage)
    {
        $config = Registry::getInstance()->get('config');
        $this->_s3_client = new S3Client([
            'version' => $storage['version'],
            'region' => $storage['region'],
            'credentials' => $storage['credentials']
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
        Writer::write($file->name, Writer::OUTPUT_FILE, $this->_log_file_name);
        try {
            $result = $this->_s3_client->putObject([
                'Bucket' => $bucket->name,
                'Key' => $file->name,
                'Body' => $file->content,
                'ContentType' => $file->getMimetype(),
                'ACL' => 'public-read'
            ]);
        } catch (S3Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_FILE, $this->_log_file_name, Writer::TYPE_ERROR);
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
     * @TODO
     * @param Bucket $bucket
     * @return bool
     */
    public function deleteAll($bucket){
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
     * TODO: AI generated, not tested
     * @param $file
     * @param $bucket
     * @return int
     * @throws Exception
     */
    public function filesize($file, $bucket){
        $result = $this->_s3_client->headObject([
            'Bucket' => $bucket->name,
            'Key' => $file->name
        ]);
        return $result['ContentLength'];
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

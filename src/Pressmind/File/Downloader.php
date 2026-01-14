<?php


namespace Pressmind\File;


use Exception;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class Downloader
{
    /**
     * @param string $url
     * @param string $targetName
     * @param bool $forceDownload Force download even if file exists
     * @return File
     * @throws Exception
     */
    public function download($url, $targetName, $forceDownload = false)
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['file_handling']['storage']);
        $file = new File($bucket);
        $file->name = $targetName;

        if ($forceDownload || !$file->exists()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Pressmind-SDK/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: */*',
                'Accept-Language: en-US,en;q=0.9',
            ]);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // Automatic decompression of gzip/deflate

            $raw = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $downloadedSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            curl_close($ch);

            if ($raw === false || $curlErrno !== 0) {
                throw new Exception('CURL error (' . $curlErrno . '): ' . $curlError . ' for URL: ' . $url);
            }

            if ($httpCode !== 200) {
                throw new Exception('HTTP error: ' . $httpCode . ' for URL: ' . $url);
            }

            if (empty($raw)) {
                throw new Exception('Downloaded file is empty (Content-Length: ' . $contentLength . ', Downloaded: ' . $downloadedSize . ') for URL: ' . $url);
            }

            $file->content = $raw;
        } else {
            $file->read();
        }

        return $file;
    }
}

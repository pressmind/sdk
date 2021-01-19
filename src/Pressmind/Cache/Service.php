<?php
namespace Pressmind\Cache;

use Exception;

class Service {
    public function cleanUp()
    {
        try {
            $redis = new \Pressmind\Cache\Adapter\Redis();
            $redis->cleanUp();
        } catch (Exception $e) {
            return 'ERROR: ' . $e->getMessage();
        }
        return 'Cleanup has run';
    }
}

<?php


namespace Pressmind\Cache\Adapter;


interface AdapterInterface
{
    public function add($pKey, $pValue, $info = null, $ttl = null);
    public function remove($pKey);
    public function exists($pKey);
    public function get($pKey);
    public function cleanUp();
}

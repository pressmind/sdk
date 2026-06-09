<?php

namespace Pressmind\Search\OpenSearch;

trait IndexNameTrait
{
    public function getIndexTemplateName($language = null)
    {
        if (empty($language)) {
            return 'index_' . $this->getConfigHash();
        }
        return 'index_' . $this->getConfigHash() . '_' . strtolower($language);
    }

    public function getConfigHash()
    {
        $config = $this->getOpenSearchConfig();
        unset($config['uri'], $config['username'], $config['password']);
        $hash = md5(serialize($config));
        $prefix = $config['index_prefix'] ?? substr(md5(realpath(__DIR__)), 0, 8);
        return $prefix . '_' . $hash;
    }

    public function getIndexPrefix()
    {
        $config = $this->getOpenSearchConfig();
        return $config['index_prefix'] ?? substr(md5(realpath(__DIR__)), 0, 8);
    }

    abstract protected function getOpenSearchConfig(): array;
}

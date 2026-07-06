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
        // Connection credentials and query-time parameters (fuzziness/prefix_length) must
        // not influence the index name, otherwise changing them would point the search to a
        // non-existent index and require a full reindex.
        unset(
            $config['uri'], $config['username'], $config['password'],
            $config['fuzziness'], $config['prefix_length']
        );
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

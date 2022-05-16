<?php

namespace Pressmind\Search\Condition\MongoDB;

class Url
{
    private $_urls;

    private $_combineOperator;

    public function __construct($urls, $combineOperator = 'OR')
    {
        if(!is_array($urls)) {
            $urls = [$urls];
        }
        $this->_urls = $urls;
        $this->_combineOperator = $combineOperator;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            if (count($this->_urls) > 1) {
                $qs = [];
                foreach ($this->_urls as $url) {
                    $qs[] = ['url' => $url];
                }
                $query['$' . strtolower($this->_combineOperator)] = $qs;
            } else {
                $query['url'] = $this->_urls[0];
            }
            return $query;
        }
        return null;
    }
}

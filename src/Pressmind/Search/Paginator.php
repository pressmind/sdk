<?php


namespace Pressmind\Search;

/**
 * Class Paginator
 * @package Pressmind - Search
 */
class Paginator
{
    /**
     * @var integer
     */
    private $_page_size;

    /**
     * @var integer|null
     */
    private $_current_page;

    /**
     * @var integer
     */
    private $_total_pages;

    /**
     * Paginator constructor.
     * @param integer $pageSize
     * @param null|integer $currentPage
     */
    public function __construct($pageSize, $currentPage = null) {
        $this->_current_page = ($currentPage == null) ? 1 : $currentPage;
        $this->_page_size = $pageSize;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        return $this->_page_size;
    }

    public function getCurrentPage()
    {
        return $this->_current_page;
    }

    public function getTotalPages()
    {
        return $this->_total_pages;
    }

    /**
     * @param $searchTotal
     * @return array
     */
    public function getLimits($searchTotal)
    {
        $this->_total_pages = ceil($searchTotal / $this->_page_size);
        return [
            'start' => ($this->_current_page - 1) * $this->_page_size,
            'length' => $this->_page_size
        ];
    }

    public static function create($pageSize, $currentPage = null)
    {
        return new self($pageSize, $currentPage);
    }
}

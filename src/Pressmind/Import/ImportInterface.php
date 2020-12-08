<?php


namespace Pressmind\Import;


interface ImportInterface
{
    /**
     * @return mixed
     */
    public function import();

    /**
     * @return array
     */
    public function getLog();

    /**
     * @return array
     */
    public function getErrors();
}

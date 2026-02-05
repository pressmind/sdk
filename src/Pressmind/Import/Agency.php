<?php


namespace Pressmind\Import;


use Pressmind\ORM\Object\AgencyToMediaObject;

class Agency extends AbstractImport implements ImportInterface
{
    /**
     * @var array
     */
    private $_agencies = [];

    /**
     * @var integer
     */
    private $_id_media_object = null;

    /**
     * Agency constructor.
     * @param array $agencies
     * @param integer $id_media_object
     */
    public function __construct($agencies, $id_media_object)
    {
        parent::__construct();
        $this->_agencies = $agencies;
        $this->_id_media_object = $id_media_object;
    }

    /**
     * @return mixed|void
     */
    public function import()
    {
        if (empty($this->_agencies)) {
            return;
        }

        // Collect all agencies and relations for batch insert
        $agencies = [];
        $relations = [];

        foreach ($this->_agencies as $import_agency) {
            $agency = new \Pressmind\ORM\Object\Agency();
            $agency->id = $import_agency->id;
            $agency->name = isset($import_agency->name) ? $import_agency->name : null;
            $agency->code = isset($import_agency->code) ? $import_agency->code : $import_agency->id;
            $agencies[] = $agency;

            $agency_to_media_object = new AgencyToMediaObject();
            $agency_to_media_object->id_media_object = $this->_id_media_object;
            $agency_to_media_object->id_agency = $agency->id;
            $relations[] = $agency_to_media_object;
        }

        // Batch insert all agencies and relations at once
        \Pressmind\ORM\Object\Agency::batchCreate($agencies);
        AgencyToMediaObject::batchCreate($relations);
    }
}

<?php


namespace Pressmind\Search\Condition;


class Agency implements ConditionInterface
{

    private $_agencies = [];

    private $_mapped_agency_count = null;

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var int
     */
    private $_sort = 6;


    public function __construct($pAgencies = [], $pMappedAgencyCount = null) {
        $this->_agencies = $pAgencies;
        $this->_mapped_agency_count = $pMappedAgencyCount;
    }

    public function getSQL()
    {
        $sql = null;
        if(is_null($this->_mapped_agency_count)) {
            $agency_strings = [];
            $term_counter = 0;
            foreach ($this->_agencies as $agency) {
                $term_counter++;
                $agency_strings[] = 'pmt2core_agency_to_media_object.id_agency = :agency' . $term_counter;
                $this->_values[':agency' . $term_counter] = $agency;
            }
            $sql = "(" . implode(' OR ', $agency_strings) . ")";
        } else if ($this->_mapped_agency_count == 0) {
            $sql = "pmt2core_media_objects.id NOT IN (SELECT id_media_object FROM pmt2core_agency_to_media_object)";
        } else {
            $sql = "(SELECT COUNT(id_media_object) FROM pmt2core_agency_to_media_object WHERE id_media_object = pmt2core_media_objects.id) >= " . $this->_mapped_agency_count;
        }
        return $sql;
    }

    public function getValues()
    {
        return $this->_values;
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getJoins()
    {
        if(is_null($this->_mapped_agency_count)) {
            return 'INNER JOIN pmt2core_agency_to_media_object ON pmt2core_agency_to_media_object.id_media_object = pmt2core_media_objects.id';
        } else {
            return null;
        }
    }

    /**
     * @return string|null
     */
    public function getJoinType()
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function getSubselectJoinTable()
    {
        return null;
    }

    public function getAdditionalFields()
    {
        return null;
    }

    public function setConfig($config)
    {
        $this->_agencies = isset($config->agencies) ? $config->agencies : [];
        $this->_mapped_agency_count = isset($config->mappedAgencyCount) ? $config->mappedAgencyCount : null;
    }

    public function getConfig() {
        return [
            'agencies' => $this->_agencies,
            'mappedAgencyCount' => $this->_mapped_agency_count,
        ];
    }

    /**
     * @param array $pAgencies
     * @param integer $pMappedAgencyCount
     * @return Agency
     */
    public static function create($pAgencies = [], $pMappedAgencyCount = null)
    {
        $object = new self($pAgencies, $pMappedAgencyCount);
        return $object;
    }
}

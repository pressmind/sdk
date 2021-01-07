<?php


namespace Pressmind\Import;



use Exception;
use Pressmind\ORM\Object\AbstractObject;

class TouristicData extends AbstractImport
{
    /**
     * @var array
     */
    private $_touristic_object_map = [
        'touristic_base' => '\Base',
        'touristic_booking_packages' => '\Booking\Package',
        'touristic_dates' => '\Date',
        'touristic_seasonal_periods' => '\SeasonalPeriod',
        'touristic_transports' => '\Transport',
        'touristic_booking_earlybirds' => '\Booking\Earlybird',
        'touristic_housing_packages' => '\Housing\Package',
        'touristic_housing_packages_description_links' => '\Housing\Package\DescriptionLink',
        'touristic_option_descriptions' => '\Option\Description',
        'touristic_options' => '\Option',
        'touristic_startingpoint_options' => '\Startingpoint\Option',
        'touristic_startingpoint_options_zip_ranges' => '\Startingpoint\Option\ZipRange',
        'touristic_startingpoints' => '\Startingpoint',
        'touristic_insurance_groups' => '\Insurance\Group',
        'touristic_insurance_to_group' => '\Insurance\InsuranceToGroup',
        'touristic_insurances' => '\Insurance',
        'touristic_insurances_price_tables' => '\Insurance\PriceTable',
    ];

    /**@var array**/
    private $_touristic_object_field_map = [
        'touristic_booking_packages' => [
            'id_insurances_groups' => 'id_insurance_group'
        ],
        'touristic_base' => [
            'id_ibe_types' => 'id_ibe_type',
            'id_saison_set' => 'id_season_set'
        ]
    ];

    /**
     * @param $data
     * @param $id_media_object
     * @param $import_type
     * @return array
     * @throws Exception
     */
    public function import($data, $id_media_object, $import_type)
    {
        $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): parsing touristic data';
        $linked_media_object_ids = [];
        $touristic_data_to_import = [];
        foreach ($data as $touristic_object_name => $touristic_objects) {
            $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Mapping ' . $touristic_object_name;
            if (is_array($touristic_objects) && count($touristic_objects) == 0) {
                $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $touristic_object_name . ' does not contain any data, skipping.';
            }
            if(isset($this->_touristic_object_map[$touristic_object_name])) {
                if(!is_array($touristic_objects)) {
                    $touristic_objects = [$touristic_objects];
                }
                foreach ($touristic_objects as $touristic_object) {
                    $class_name = '\Pressmind\ORM\Object\Touristic' . $this->_touristic_object_map[$touristic_object_name];
                    foreach ($touristic_object as $key => $value) {
                        if (isset($this->_touristic_object_field_map[$touristic_object_name][$key])) {
                            $new_key = $this->_touristic_object_field_map[$touristic_object_name][$key];
                            $touristic_object->$new_key = $value;
                            unset($touristic_object->$key);
                        }
                    }
                    try {
                        /**@var AbstractObject $object * */
                        $object = new $class_name();
                        $object->fromStdClass($touristic_object);
                        $touristic_data_to_import[] = $object;
                        $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping successfull.';
                    } catch (Exception $e) {
                        $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping failed: ' . $e->getMessage();
                        $this->_errors[] = 'Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping failed: ' . $e->getMessage();
                    }
                    if ($touristic_object_name == 'touristic_housing_packages_description_links') {
                        if (isset($touristic_object->id_media_object) && !empty($touristic_object->id_media_object)) {
                            $linked_media_object_ids[] = $touristic_object->id_media_object;
                        }
                    }
                    unset($object);
                    $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Object removed from heap';
                }
            }
        }
        $starting_point_ids = [];
        foreach ($touristic_data_to_import as $touristic_object_to_import) {
            $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): inserting touristic data for ' . get_class($touristic_object_to_import);
            /**@var AbstractObject $touristic_object_to_import * */
            if(is_a($touristic_object_to_import,'Pressmind\ORM\Object\Touristic\Startingpoint')) {
                $starting_point_ids[] = $touristic_object_to_import->id;
            }
            try {
                $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): deleting old data ' . get_class($touristic_object_to_import);
                $touristic_object_to_import->delete();
                $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): writing new data ' . get_class($touristic_object_to_import);
                $touristic_object_to_import->create();
                $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' created.';
            } catch (Exception $e) {
                $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' creation failed: ' . $e->getMessage();
                $this->_errors[] = 'Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' creation failed: ' . $e->getMessage();
            }
            unset($touristic_object_to_import);
            unset($touristic_data);
            $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Object removed from heap';
        }

        return [
            'linked_media_object_ids' => $linked_media_object_ids,
            'starting_point_ids' => $starting_point_ids
        ];
    }
}

<?php
namespace Pressmind\Import;

use Exception;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToGroup;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToInsurance;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToPriceTable;
use Pressmind\ORM\Object\Touristic\Option\Discount;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\Registry;

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
        'touristic_additional_insurances' => '\Insurance',
        'touristic_insurances_price_tables' => '\Insurance\PriceTable',
        'touristic_insurances_to_price_table' => '\Insurance\InsuranceToPriceTable',
        'touristic_option_discounts' => '\Option\Discount'
    ];

    /**@var array**/
    private $_touristic_object_field_map = [
        'touristic_booking_packages' => [
            'id_insurances_groups' => 'id_insurance_group'
        ],
        'touristic_base' => [
            'id_ibe_types' => 'id_ibe_type',
            'id_saison_set' => 'id_season_set'
        ],
        'touristic_dates' => [
            'id_earlybird_discount' => 'id_early_bird_discount_group'
        ],
        'touristic_transports' => [
            'id_early_bird' => 'id_early_bird_discount_group'
        ],
        'touristic_options' => [
            'id_touristic_option_discounts' => 'id_touristic_option_discount'
        ],
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
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): parsing touristic data';
        $linked_media_object_ids = [];
        $touristic_data_to_import = [];
        if(isset($data->touristic_housing_packages_description_links)){
            unset($data->touristic_housing_packages_description_links); // orphaned in pm2, will be removed in pm2 soon.
        }
        foreach ($data as $touristic_object_name => $touristic_objects) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Mapping ' . $touristic_object_name;
            if (is_array($touristic_objects) && count($touristic_objects) == 0) {
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $touristic_object_name . ' does not contain any data, skipping.';
            }
            if(isset($this->_touristic_object_map[$touristic_object_name])) {
                if(!is_array($touristic_objects)) {
                    $touristic_objects = [$touristic_objects];
                }
                foreach ($touristic_objects as $touristic_object) {
                    if($touristic_object_name == 'touristic_additional_insurances') {
                        $main_insurance_id = $touristic_object->id_insurance;
                        unset($touristic_object->id_insurance);
                        $insuranceToinsurance = new InsuranceToInsurance();
                        $insuranceToinsurance->id_insurance = $main_insurance_id;
                        $insuranceToinsurance->id_sub_insurance = $touristic_object->id;
                        $insuranceToinsurance->delete();
                        $insuranceToinsurance->create();
                    }
                    if($touristic_object_name == 'touristic_option_discounts') {
                        unset($touristic_object->import_code);
                    }
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
                        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping successfull.';
                    } catch (Exception $e) {
                        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping failed: ' . $e->getMessage();
                        $this->_errors[] = 'Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . $class_name . ' mapping failed: ' . $e->getMessage();
                    }
                    if ($touristic_object_name == 'touristic_housing_packages_description_links') {
                        if (isset($touristic_object->id_media_object) && !empty($touristic_object->id_media_object)) {
                            $linked_media_object_ids[] = $touristic_object->id_media_object;
                        }
                    }
                    unset($object);
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Object removed from heap';
                }
            }
        }
        $this->_removeInsuranceOrphans($data);
        $this->_removeStartingPointOrphans($data);
        $starting_point_ids = [];
        foreach ($touristic_data_to_import as $touristic_object_to_import) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): inserting touristic data for ' . get_class($touristic_object_to_import);
            /**@var AbstractObject $touristic_object_to_import * */
            if(is_a($touristic_object_to_import,'Pressmind\ORM\Object\Touristic\Startingpoint')) {
                $starting_point_ids[] = $touristic_object_to_import->id;
            }
            try {
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): deleting old data ' . get_class($touristic_object_to_import);
                $touristic_object_to_import->delete();
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): writing new data ' . get_class($touristic_object_to_import);
                $touristic_object_to_import->create();
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' created.';
            } catch (Exception $e) {
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' creation failed: ' . $e->getMessage();
                $this->_errors[] = 'Importer::_importMediaObjectTouristicData(' . $id_media_object . '): ' . get_class($touristic_object_to_import) . ' creation failed: ' . $e->getMessage();
            }
            unset($touristic_object_to_import);
            unset($touristic_data);
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): Object removed from heap';
        }

        return [
            'linked_media_object_ids' => $linked_media_object_ids,
            'starting_point_ids' => $starting_point_ids
        ];
    }

    /**
     * @param $data
     * @return void
     * @throws Exception
     */
    private function _removeInsuranceOrphans($data){
        /**
         * @var $db AdapterInterface
         */
        $db = Registry::getInstance()->get('db');
        $insurance_to_group = [];
        $insurances_to_price_table = [];
        foreach($data as $touristic_object_name => $touristic_objects){
            if($touristic_object_name == 'touristic_insurance_to_group'){
                foreach($touristic_objects as $item){
                    $insurance_to_group[$item->id_insurance_group][] = $item;
                }
            }
            if($touristic_object_name == 'touristic_insurances_to_price_table'){
                foreach($touristic_objects as $item){
                    $insurances_to_price_table[$item->id_insurance][] = $item;
                }
            }
        }

        if (!empty($insurance_to_group)) {
            $group_ids = array_keys($insurance_to_group);
            $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
            // Fetch all stored items for all groups at once
            $allStoredItems = $db->fetchAll(
                "SELECT id_insurance, id_insurance_group FROM pmt2core_touristic_insurance_to_group WHERE id_insurance_group IN ($placeholders)",
                $group_ids
            );
            $items_to_delete = [];
            foreach ($allStoredItems as $storedItem) {
                $id_insurance_group = $storedItem->id_insurance_group;
                $id_insurance = $storedItem->id_insurance;
                if (isset($insurance_to_group[$id_insurance_group])) {
                    $result = array_filter($insurance_to_group[$id_insurance_group], function($v) use ($id_insurance) {
                        return $v->id_insurance == $id_insurance;
                    });
                    if (count($result) == 0) {
                        $items_to_delete[] = ['id_insurance' => $id_insurance, 'id_insurance_group' => $id_insurance_group];
                    }
                }
            }
            foreach ($items_to_delete as $item) {
                $db->execute('DELETE FROM pmt2core_touristic_insurance_to_group WHERE id_insurance = ? AND id_insurance_group = ?',
                    [$item['id_insurance'], $item['id_insurance_group']]);
            }
        }
        if (!empty($insurances_to_price_table)) {
            $insurance_ids = array_keys($insurances_to_price_table);
            $placeholders = implode(',', array_fill(0, count($insurance_ids), '?'));
            $allStoredItems = $db->fetchAll(
                "SELECT id_insurance, id_price_table FROM pmt2core_touristic_insurance_to_price_table WHERE id_insurance IN ($placeholders)",
                $insurance_ids
            );
            $items_to_delete = [];
            foreach ($allStoredItems as $storedItem) {
                $id_insurance = $storedItem->id_insurance;
                $id_price_table = $storedItem->id_price_table;
                if (isset($insurances_to_price_table[$id_insurance])) {
                    $result = array_filter($insurances_to_price_table[$id_insurance], function($v) use ($id_price_table) {
                        return $v->id_price_table == $id_price_table;
                    });
                    if (count($result) == 0) {
                        $items_to_delete[] = ['id_insurance' => $id_insurance, 'id_price_table' => $id_price_table];
                    }
                }
            }
            foreach ($items_to_delete as $item) {
                $db->execute('DELETE FROM pmt2core_touristic_insurance_to_price_table WHERE id_insurance = ? AND id_price_table = ?',
                    [$item['id_insurance'], $item['id_price_table']]);
            }
        }

        $db->execute('DELETE FROM pmt2core_touristic_insurances_price_tables WHERE id NOT IN (SELECT id_price_table FROM pmt2core_touristic_insurance_to_price_table)');
    }

    /**
     * @param $data
     * @return void
     * @throws Exception
     */
    private function _removeStartingPointOrphans($data){
        /**
         * @var $db AdapterInterface
         */
        $db = Registry::getInstance()->get('db');
        $startingpoints_options = [];
        foreach($data as $touristic_object_name => $touristic_objects){
            if($touristic_object_name == 'touristic_startingpoints_options'){
                foreach($touristic_objects as $item){
                    $startingpoints_options[$item->id_starting_point][] = $item;
                }
            }
        }

        // Optimized: Batch fetch all needed data upfront, then batch delete
        if (!empty($startingpoints_options)) {
            $startingpoint_ids = array_keys($startingpoints_options);
            $placeholders = implode(',', array_fill(0, count($startingpoint_ids), '?'));
            // Fetch all stored items for all startingpoints at once
            $allStoredItems = $db->fetchAll(
                "SELECT id, id_startingpoint FROM pmt2core_touristic_startingpoint_options WHERE id_startingpoint IN ($placeholders)",
                $startingpoint_ids
            );
            $ids_to_delete = [];
            foreach ($allStoredItems as $storedItem) {
                $id_startingpoint = $storedItem->id_startingpoint;
                $id_startingpoint_option = $storedItem->id;
                if (isset($startingpoints_options[$id_startingpoint])) {
                    $result = array_filter($startingpoints_options[$id_startingpoint], function($v) use ($id_startingpoint_option) {
                        return $v->id == $id_startingpoint_option;
                    });
                    if (count($result) == 0) {
                        $ids_to_delete[] = $storedItem->id;
                    }
                }
            }
            if (!empty($ids_to_delete)) {
                $delete_placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                $db->execute("DELETE FROM pmt2core_touristic_startingpoint_options WHERE id IN ($delete_placeholders)", $ids_to_delete);
            }
        }
    }
}

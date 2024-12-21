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
        'touristic_booking_earlybirds' => '\EarlyBirdDiscountGroup',
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
                    if($touristic_object_name == 'touristic_additional_insurances') {
                        $main_insurance_id = $touristic_object->id_insurance;
                        unset($touristic_object->id_insurance);
                        $insuranceToinsurance = new InsuranceToInsurance();
                        $insuranceToinsurance->id_insurance = $main_insurance_id;
                        $insuranceToinsurance->id_sub_insurance = $touristic_object->id;
                        $insuranceToinsurance->delete();
                        $insuranceToinsurance->create();
                    }
                    if($touristic_object_name == 'touristic_booking_earlybirds') {
                        $items = (isset($touristic_object->scales) && is_array($touristic_object->scales)) ? $touristic_object->scales : [];
                        if(!isset($touristic_object->name)) {
                            $touristic_object->name = $touristic_object->import_code;
                        }
                        unset($touristic_object->id_date);
                        unset($touristic_object->import_code);
                        unset($touristic_object->scales);
                        foreach ($items as $item) {
                            $new_item = new Item();
                            $item->id_early_bird_discount_group = $touristic_object->id;
                            unset($item->id_booking_package);
                            unset($item->id_media_object);
                            $new_item->fromStdClass($item);
                            $new_item->create();
                        }
                        /*$touristic_object->id_early_bird_discount_group = md5($touristic_object->id_booking_package);
                        unset($touristic_object->id_booking_package);
                        unset($touristic_object->id_media_object);*/

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
        $this->_removeInsuranceOrphans($data);
        $this->_removeStartingPointOrphans($data);
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
        if(!empty($insurance_to_group)){
            foreach($insurance_to_group as $id_insurance_group => $groupItem){
                $storedItems = InsuranceToGroup::listAll(['id_insurance_group' => $id_insurance_group]);
                foreach($storedItems as $storedItem){
                    $id_insurance = $storedItem->id_insurance;
                    $result = array_filter($groupItem, function($v) use ($id_insurance) {
                        return $v->id_insurance == $id_insurance;
                    });
                    if(count($result) == 0) {
                        $db->execute('delete from pmt2core_touristic_insurance_to_group where id_insurance = ? and id_insurance_group = ?', [$storedItem->id_insurance, $id_insurance_group]);
                    }
                }
            }
        }
        if(!empty($insurances_to_price_table)){
            foreach($insurances_to_price_table as $id_insurance => $groupItem){
                $storedItems = InsuranceToPriceTable::listAll(['id_insurance' => $id_insurance]);
                foreach($storedItems as $storedItem){
                    $id_price_table = $storedItem->id_price_table;
                    $result = array_filter($groupItem, function($v) use ($id_price_table) {
                        return $v->id_price_table == $id_price_table;
                    });
                    if(count($result) == 0) {
                        $db->execute('delete from pmt2core_touristic_insurance_to_price_table where id_insurance = ? and id_price_table = ?', [$id_insurance, $storedItem->id_price_table]);
                    }
                }
            }
        }
        $db->execute('delete from pmt2core_touristic_insurances_price_tables where id not in (select id_price_table from pmt2core_touristic_insurance_to_price_table)');
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
        if(!empty($startingpoints_options)){
            foreach($startingpoints_options as $id_startingpoint => $startingPointOption){
                $storedItems = Startingpoint\Option::listAll(['id_startingpoint' => $id_startingpoint]);
                foreach($storedItems as $storedItem){
                    $id_startingpoint_option = $storedItem->id;
                    $result = array_filter($startingPointOption, function($v) use ($id_startingpoint_option) {
                        return $v->id == $id_startingpoint_option;
                    });
                    if(count($result) == 0) {
                        $db->execute('delete from pmt2core_touristic_startingpoint_options where id = ? ', [$storedItem->id]);
                    }
                }
            }
        }
    }
}

<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\ORM\Object\Import\Queue;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\Registry;

class Import
{
    /**
     * Add a media object to the import queue.
     *
     * @param $parameters
     *   - id_media_object: The media object ID to add (alternative to code)
     *   - code: The media object code to search for (alternative to id_media_object)
     *   - queue_action (optional): The action to perform ('mediaobject' or 'touristic', default: 'mediaobject')
     * @return array
     * @throws Exception
     */
    public function addToQueue($parameters)
    {
        $queue_action = isset($parameters['queue_action']) ? $parameters['queue_action'] : 'mediaobject';
        if (!in_array($queue_action, ['mediaobject', 'touristic'])) {
            $queue_action = 'mediaobject';
        }
        if (isset($parameters['id_media_object']) && preg_match('/^[0-9]+$/', $parameters['id_media_object'])) {
            $id_media_object = (int)$parameters['id_media_object'];
            if (Queue::exists($id_media_object)) {
                return [
                    'success' => true,
                    'msg' => 'Info: ID ' . $id_media_object . ' is already in queue',
                    'data' => null
                ];
            }
            try {
                Queue::addToQueue($id_media_object, 'api_import', $queue_action);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'msg' => 'Error: Can not add to queue: ' . $e->getMessage(),
                    'data' => null
                ];
            }
            return [
                'success' => true,
                'msg' => 'Success: object added to queue with action: ' . $queue_action,
                'data' => null
            ];
        }
        elseif (isset($parameters['code']) && !empty($parameters['code'])) {
            $code = trim($parameters['code']);
            $mediaObjects = MediaObject::getByCode($code);
            if (empty($mediaObjects)) {
                return [
                    'success' => false,
                    'msg' => 'Error: No media objects found with code: ' . $code,
                    'data' => null
                ];
            }
            $added = 0;
            $skipped = 0;
            foreach ($mediaObjects as $mediaObject) {
                $id = $mediaObject->id;
                if (Queue::exists($id)) {
                    $skipped++;
                    continue;
                }
                try {
                    Queue::addToQueue($id, 'api_import', $queue_action);
                    $added++;
                } catch (Exception $e) {
                    // Log error but continue with other objects
                }
            }
            return [
                'success' => true,
                'msg' => 'Success: ' . $added . ' object(s) added to queue, ' . $skipped . ' already in queue',
                'data' => ['added' => $added, 'skipped' => $skipped, 'code' => $code]
            ];
        }
        else {
            return [
                'success' => false,
                'msg' => 'Error: Parameter id_media_object or code is required',
                'data' => null
            ];
        }
    }

    public function fullimport($parameters)
    {
        try{
            $Import = new \Pressmind\Import();
            $Import->getIDsToImport();
        }catch (Exception $e) {
            return [
                'success' => false,
                'msg' => 'Error: '.$e->getMessage(),
                'data' => null
            ];
        }
        return [
            'success' => true,
            'msg' => 'Success: objects added to queue',
            'data' => null
        ];
    }

    /**
     * Add all media objects to the import queue with touristic action.
     * This will only update touristic data without reimporting the full media object.
     *
     * @param $parameters
     * @return array
     */
    public function fullimportTouristic($parameters)
    {
        try {
            $Import = new \Pressmind\Import('fullimport_touristic');
            $Import->getIDsToImport();
        } catch (Exception $e) {
            return [
                'success' => false,
                'msg' => 'Error: ' . $e->getMessage(),
                'data' => null
            ];
        }
        return [
            'success' => true,
            'msg' => 'Success: touristic import objects added to queue',
            'data' => null
        ];
    }

    /**
     * Import touristic data (booking packages) by media object code.
     * The payload contains the complete ORM model with relations.
     * Global entities (Insurance Groups, Startingpoints, etc.) are referenced by ID only.
     *
     * @param array $parameters
     * @return array
     */
    public function touristicByCode($parameters)
    {
        // Validate input - parameters come directly from JSON body (parsed as array)
        if (empty($parameters['code'])) {
            return ['success' => false, 'msg' => 'Error: Parameter code is required', 'data' => null];
        }
        if (empty($parameters['booking_packages']) || !is_array($parameters['booking_packages'])) {
            return ['success' => false, 'msg' => 'Error: Parameter booking_packages must be an array', 'data' => null];
        }
        
        $code = trim($parameters['code']);
        $booking_packages_data = $parameters['booking_packages'];
        
        // Find all MediaObjects with this code
        $mediaObjects = MediaObject::getByCode($code);
        if (empty($mediaObjects)) {
            return ['success' => false, 'msg' => 'Error: No media objects found with code: ' . $code, 'data' => null];
        }
        
        $results = [];
        $config = Registry::getInstance()->get('config');
        $db = Registry::getInstance()->get('db');
        
        // Process each MediaObject
        foreach ($mediaObjects as $mediaObject) {
            $id_media_object = $mediaObject->getId();
            $result = ['id_media_object' => $id_media_object, 'success' => true, 'errors' => []];
            
            try {
                // Delete old booking packages with all hasMany relations (cascading)
                $mediaObject->setReadRelations(true);
                $mediaObject->readRelations();
                foreach ($mediaObject->booking_packages as $oldPackage) {
                    $oldPackage->delete(true);
                }
                
                // Clear cheapest price speed entries
                $db->delete('pmt2core_cheapest_price_speed', ['id_media_object = ?', $id_media_object]);
                
                // Import new booking packages from payload
                foreach ($booking_packages_data as $packageData) {
                    // Convert array to stdClass for ORM compatibility
                    $packageStdClass = json_decode(json_encode($packageData));
                    
                    $package = new Package();
                    $package->fromStdClass($packageStdClass);
                    $package->id_media_object = $id_media_object;
                    
                    // Ensure id_media_object is set on all nested relations
                    $this->setIdMediaObjectOnRelations($package, $id_media_object);
                    
                    $package->create();
                }
                
                // Recalculate cheapest price
                $mediaObject = new MediaObject($id_media_object);
                $mediaObject->setReadRelations(true);
                $mediaObject->readRelations();
                $mediaObject->insertCheapestPrice();
                
                // Update cache if enabled
                if (!empty($config['cache']['enabled']) && in_array('OBJECT', $config['cache']['types'])) {
                    $mediaObject->updateCache($id_media_object);
                }
                
                // Update MongoDB index if enabled
                if (!empty($config['data']['search_mongodb']['enabled'])) {
                    $mediaObject->createMongoDBIndex();
                    $mediaObject->createMongoDBCalendar();
                }
                
                // Update OpenSearch index if enabled
                if (!empty($config['data']['search_opensearch']['enabled'])) {
                    $mediaObject->createOpenSearchIndex();
                }
                
            } catch (Exception $e) {
                $result['success'] = false;
                $result['errors'][] = $e->getMessage();
                Writer::write('TouristicImport error for ' . $id_media_object . ': ' . $e->getMessage(), 
                    Writer::OUTPUT_FILE, 'touristic_import', Writer::TYPE_ERROR);
            }
            
            $results[] = $result;
        }
        
        $allSuccess = array_reduce($results, fn($carry, $r) => $carry && $r['success'], true);
        
        return [
            'success' => $allSuccess,
            'msg' => $allSuccess ? 'Touristic import completed' : 'Touristic import completed with errors',
            'data' => [
                'code' => $code,
                'processed' => count($results),
                'results' => $results
            ]
        ];
    }

    /**
     * Recursively set id_media_object on all nested relation objects.
     *
     * @param Package $package
     * @param int $id_media_object
     * @return void
     */
    private function setIdMediaObjectOnRelations($package, $id_media_object)
    {
        // Set on dates
        if (!empty($package->dates) && is_array($package->dates)) {
            foreach ($package->dates as $date) {
                $date->id_media_object = $id_media_object;
            }
        }
        
        // Set on housing_packages and their options
        if (!empty($package->housing_packages) && is_array($package->housing_packages)) {
            foreach ($package->housing_packages as $housingPackage) {
                $housingPackage->id_media_object = $id_media_object;
                if (!empty($housingPackage->options) && is_array($housingPackage->options)) {
                    foreach ($housingPackage->options as $option) {
                        $option->id_media_object = $id_media_object;
                    }
                }
            }
        }
        
        // Set on seasonal_periods
        if (!empty($package->seasonal_periods) && is_array($package->seasonal_periods)) {
            foreach ($package->seasonal_periods as $seasonalPeriod) {
                $seasonalPeriod->id_media_object = $id_media_object;
            }
        }
        
        // Set on sightseeings (options)
        if (!empty($package->sightseeings) && is_array($package->sightseeings)) {
            foreach ($package->sightseeings as $option) {
                $option->id_media_object = $id_media_object;
            }
        }
        
        // Set on tickets (options)
        if (!empty($package->tickets) && is_array($package->tickets)) {
            foreach ($package->tickets as $option) {
                $option->id_media_object = $id_media_object;
            }
        }
        
        // Set on extras (options)
        if (!empty($package->extras) && is_array($package->extras)) {
            foreach ($package->extras as $option) {
                $option->id_media_object = $id_media_object;
            }
        }
    }

}


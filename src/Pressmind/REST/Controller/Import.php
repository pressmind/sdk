<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\ORM\Object\Import\Queue;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
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

}


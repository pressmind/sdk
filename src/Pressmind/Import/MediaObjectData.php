<?php


namespace Pressmind\Import;

use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Attachment;
use Pressmind\ORM\Object\AttachmentToMediaObject;
use Pressmind\ORM\Object\MediaType\Factory;
use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Registry;
use Pressmind\System\SchemaMigrator;
use stdClass;

class MediaObjectData extends AbstractImport implements ImportInterface
{
    /**
     * @var array
     */
    private $_var_names_to_be_ignored;

    /**
     * @var stdClass
     */
    private $_data;

    /**
     * @var int
     */
    private $_id_media_object;

    /**
     * @var string
     */
    private $_import_type;

    /**
     * @var bool
     */
    private $_import_linked_objects;

    /**
     * @var array Cached config for performance
     */
    private $_config = null;

    /**
     * MediaObjectData constructor.
     * @param stdClass $data
     * @param integer $id_media_object
     * @param string $import_type
     * @param boolean $import_linked_objects
     */
    public function __construct($data, $id_media_object, $import_type, $import_linked_objects = true)
    {
        parent::__construct();
        $this->_data = $data;
        $this->_id_media_object = $id_media_object;
        $this->_import_type = $import_type;
        $this->_import_linked_objects = $import_linked_objects;
        $this->_var_names_to_be_ignored = [];
        // Cache config for performance
        $this->_config = Registry::getInstance()->get('config');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function import() {

        $category_tree_ids = [];
        $conf = $this->_config;
        $default_language = $conf['data']['languages']['default'];
        $allowed_media_objects = array_keys($conf['data']['media_types']);
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' MediaObjectData::import(' . $this->_id_media_object . '): Importing media object data';

        // Schema Migration: Check for new fields and migrate if configured
        // This must happen BEFORE we try to import data, so DB columns exist
        if (isset($this->_data->data) && is_array($this->_data->data)) {
            try {
                $migrationResult = SchemaMigrator::migrateIfNeeded(
                    $this->_data->id_media_objects_data_type,
                    $this->_data->data
                );

                if ($migrationResult['migrated']) {
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' MediaObjectData::import(' . $this->_id_media_object . '): Schema migrated, added fields: ' .
                        implode(', ', array_keys($migrationResult['fields']));
                }

                // log_only mode: Remove unknown fields from import data before processing
                if (!empty($migrationResult['ignore_fields'])) {
                    foreach ($this->_data->data as $dataField) {
                        if (in_array(HelperFunctions::human_to_machine($dataField->var_name), $migrationResult['ignore_fields'])) {
                            $dataField->sections = []; // Empty sections so the field is effectively ignored
                        }
                    }
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' MediaObjectData::import(' . $this->_id_media_object . '): Ignored unknown fields: ' .
                        implode(', ', $migrationResult['ignore_fields']);
                }
            } catch (Exception $e) {
                // Re-throw schema exceptions to stop the import if mode is 'abort'
                throw $e;
            }
        }
        $values = [];
        $linked_media_object_ids = [];
        $languages = [];
        foreach ($this->_data->data as $data_field) {
            if (is_array($data_field->sections)) {
                foreach ($data_field->sections as $section) {
                    $var_name = HelperFunctions::human_to_machine($data_field->var_name);
                    $section->language = empty($section->language) ? $default_language : $section->language;
                    $language = empty($section->language) ? $default_language : $section->language;
                    $language = strtolower($language);
                    if(!in_array($var_name, $this->_var_names_to_be_ignored) && in_array($language, $conf['data']['languages']['allowed'])) {
                        $section_name = $section->name;
                        if(isset($conf['data']['sections']['replace']) && !empty($conf['data']['sections']['replace']['regular_expression'])) {
                            $section_name = preg_replace($conf['data']['sections']['replace']['regular_expression'], $conf['data']['sections']['replace']['replacement'], $section_name);
                        }
                        $column_name = $var_name . '_' . HelperFunctions::human_to_machine($section_name);

                        if (!isset($values[$language])) $values[$language] = [];
                        $section_id = $section->id;
                        $value = null;
                        if($data_field->type == 'categorytree' && isset($data_field->value)) {
                            $value = $data_field->value;
                            if(!empty($value)){
                                $value->id_object_type = $this->_data->id_media_objects_data_type;
                            }
                        } else if($data_field->type == 'key_value') {
                            if(!empty($data_field->value)) {
                                $value = [
                                    'columns' => $data_field->columns,
                                    'values' => $data_field->value->$section_id
                                ];
                            }
                        } else if(isset($data_field->value) && isset($data_field->value->$section_id)) {
                            $value = $data_field->value->$section_id;
                        }
                        $import_linked_objects = true;
                        if(!empty($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) && is_array($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) && in_array($column_name, $conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type])){
                            $this->_log[] = Writer::write('                               MediaObjectData::import(' . $this->_id_media_object . '): object_link import is disabled for field "'.$column_name.'". See config (data.disable_recursive_import). This is not an error, just a msg.', Writer::OUTPUT_SCREEN, 'import', Writer::TYPE_INFO);
                            $import_linked_objects = false;
                        }
                        if($data_field->type == 'objectlink' && !is_null($value) && is_a($value,'stdClass') &&
                            isset($value->objects) && is_array($value->objects) && $this->_import_linked_objects == true) {
                            if(in_array($data_field->id_object_type, $allowed_media_objects)) {
                                if($import_linked_objects == true){
                                    foreach ($value->objects as $linked_media_object_id) {
                                        $linked_media_object_ids[] = $linked_media_object_id;
                                    }
                                }
                            }else{
                                $this->_log[] = Writer::write('                               MediaObjectData::import(' . $this->_id_media_object . '): object_link/s ('.$data_field->var_name.') found, but object_type ('.$data_field->id_object_type.') is not allowed or missing. See config (data.media_types). This is not an error, just a msg.', Writer::OUTPUT_SCREEN, 'import', Writer::TYPE_INFO);
                            }
                        }
                        // Process attachments for text/wysiwyg fields
                        if (($data_field->type == 'text' || $data_field->type == 'wysiwyg') &&
                            isset($data_field->attachments) && is_array($data_field->attachments) && !empty($data_field->attachments)) {
                            $value = $this->_processAttachments(
                                $data_field->attachments,
                                $value,
                                $var_name,
                                $section_name,
                                $language
                            );
                        }

                        $values[$language]['language'] = strtolower($language);
                        $values[$language]['id_media_object'] = $this->_id_media_object;
                        $values[$language][$column_name] = $value;
                        $languages[] = strtolower($language);
                    }
                }
            }
            if($data_field->type == 'categorytree' && isset($data_field->value) && isset($data_field->value->id_category)) {
                $category_tree_ids[] = $data_field->value->id_category;
            }
        }
        foreach ($values as $language => $section_data) {
            try {
                $media_object_data = Factory::createById($this->_data->id_media_objects_data_type);
                $media_object_data->fromImport($section_data);
                $media_object_data->create();
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Object ' . get_class($media_object_data) . ' created with ID: ' . $this->_id_media_object;
                unset($media_object_data);
            } catch (Exception $e) {
                $this->_log[] = $e->getMessage();
            }
        }
        unset($values);
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Heap cleaned up';

        return [
            'linked_media_object_ids' => $linked_media_object_ids,
            'category_tree_ids' => $category_tree_ids,
            'languages' => array_unique($languages)
        ];
    }

    /**
     * Process attachments from text/wysiwyg fields
     * - Creates/updates Attachment records
     * - Downloads files synchronously when hash changes
     * - Creates AttachmentToMediaObject relations
     * - Rewrites HTML links with local URLs
     *
     * @param array $attachments Array of attachment objects from API
     * @param string|null $htmlValue The HTML content containing links
     * @param string $varName Field var_name
     * @param string $sectionName Section name
     * @param string $language Language code
     * @return string|null The modified HTML with rewritten URLs
     */
    private function _processAttachments($attachments, $htmlValue, $varName, $sectionName, $language)
    {
        if (empty($attachments) || !is_string($htmlValue)) {
            return $htmlValue;
        }

        $this->_log[] = Writer::write(
            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Processing ' . count($attachments) . ' attachment(s) for field ' . $varName,
            Writer::OUTPUT_BOTH,
            'import',
            Writer::TYPE_INFO
        );

        $config = $this->_config;
        $attachmentsMap = [];
        $relationsToCreate = []; // Collect relations for batch insert

        // Delete existing relations for this field/section/language
        AttachmentToMediaObject::deleteByMediaObjectField(
            $this->_id_media_object,
            $varName,
            $sectionName,
            $language
        );

        foreach ($attachments as $attachmentData) {
            try {
                $attachmentId = $attachmentData->id;
                $newHash = isset($attachmentData->hash) ? $attachmentData->hash : null;

                // Try to load existing attachment
                $attachment = new Attachment();
                try {
                    $attachment->read($attachmentId);
                    $existingHash = $attachment->hash;
                } catch (Exception $e) {
                    $existingHash = null;
                }

                $needsDownload = false;

                if (empty($attachment->id)) {
                    // New attachment
                    $attachment->id = $attachmentId;
                    $attachment->name = $attachmentData->name;
                    $attachment->path = isset($attachmentData->path) ? $attachmentData->path : '/';
                    $attachment->hash = $newHash;
                    $attachment->mime_type = isset($attachmentData->mime_type) ? $attachmentData->mime_type : null;
                    $attachment->file_size = isset($attachmentData->file_size) ? $attachmentData->file_size : 0;
                    $attachment->drive_url = isset($attachmentData->drive_url) ? $attachmentData->drive_url : null;
                    $attachment->folder_id = isset($attachmentData->folder_id) ? $attachmentData->folder_id : null;
                    $attachment->description = isset($attachmentData->description) ? $attachmentData->description : null;
                    $attachment->tmp_url = $attachment->drive_url;
                    $attachment->download_successful = false;
                    $attachment->create();
                    $needsDownload = true;
                    $this->_log[] = Writer::write(
                        $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Created new attachment ' . $attachmentId,
                        Writer::OUTPUT_BOTH,
                        'import',
                        Writer::TYPE_INFO
                    );
                } else if ($newHash !== null && $existingHash !== $newHash) {
                    // Hash changed - update and re-download
                    $attachment->name = $attachmentData->name;
                    $attachment->path = isset($attachmentData->path) ? $attachmentData->path : $attachment->path;
                    $attachment->hash = $newHash;
                    $attachment->mime_type = isset($attachmentData->mime_type) ? $attachmentData->mime_type : $attachment->mime_type;
                    $attachment->file_size = isset($attachmentData->file_size) ? $attachmentData->file_size : $attachment->file_size;
                    $attachment->drive_url = isset($attachmentData->drive_url) ? $attachmentData->drive_url : $attachment->drive_url;
                    $attachment->folder_id = isset($attachmentData->folder_id) ? $attachmentData->folder_id : $attachment->folder_id;
                    $attachment->description = isset($attachmentData->description) ? $attachmentData->description : $attachment->description;
                    $attachment->tmp_url = $attachment->drive_url;
                    $attachment->download_successful = false;
                    $attachment->update();
                    $needsDownload = true;
                    $this->_log[] = Writer::write(
                        $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Updated attachment ' . $attachmentId . ' (hash changed)',
                        Writer::OUTPUT_BOTH,
                        'import',
                        Writer::TYPE_INFO
                    );
                } else {
                    // Hash unchanged - but check if previous download failed
                    if (!$attachment->download_successful) {
                        $needsDownload = true;
                        $this->_log[] = Writer::write(
                            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Attachment ' . $attachmentId . ' unchanged, but retrying failed download',
                            Writer::OUTPUT_BOTH,
                            'import',
                            Writer::TYPE_INFO
                        );
                    } else {
                        $this->_log[] = Writer::write(
                            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Attachment ' . $attachmentId . ' unchanged, skipping download',
                            Writer::OUTPUT_BOTH,
                            'import',
                            Writer::TYPE_INFO
                        );
                    }
                }

                // Download file synchronously if needed
                if ($needsDownload && !empty($attachment->drive_url)) {
                    try {
                        $this->_log[] = Writer::write(
                            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Downloading ' . $attachment->getStoragePath() . ' from ' . $attachment->drive_url,
                            Writer::OUTPUT_BOTH,
                            'import',
                            Writer::TYPE_INFO
                        );
                        $attachment->downloadOriginal();
                        $this->_log[] = Writer::write(
                            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Download successful - ' . $attachment->getStoragePath() . ' (' . number_format($attachment->file_size / 1024, 2) . ' KB)',
                            Writer::OUTPUT_BOTH,
                            'import',
                            Writer::TYPE_INFO
                        );
                    } catch (Exception $e) {
                        $this->_log[] = Writer::write(
                            $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Download FAILED for ' . $attachment->getStoragePath() . ': ' . $e->getMessage(),
                            Writer::OUTPUT_BOTH,
                            'import',
                            Writer::TYPE_ERROR
                        );
                    }
                }

                // Collect relation for batch insert
                $relation = new AttachmentToMediaObject();
                $relation->id_attachment = $attachmentId;
                $relation->id_media_object = $this->_id_media_object;
                $relation->var_name = $varName;
                $relation->section_name = $sectionName;
                $relation->language = $language;
                $relationsToCreate[] = $relation;

                // Store for URL rewriting
                $attachmentsMap[$attachmentId] = [
                    'path' => $attachment->path,
                    'name' => $attachment->name
                ];

            } catch (Exception $e) {
                $this->_log[] = Writer::write(
                    $this->_getElapsedTimeAndHeap() . ' MediaObjectData::_processAttachments(' . $this->_id_media_object . '): Error processing attachment: ' . $e->getMessage(),
                    Writer::OUTPUT_FILE,
                    'import',
                    Writer::TYPE_ERROR
                );
            }
        }

        // Batch insert all relations at once
        if (!empty($relationsToCreate)) {
            AttachmentToMediaObject::batchCreate($relationsToCreate);
        }

        // Rewrite URLs in HTML
        if (!empty($attachmentsMap)) {
            $httpSrc = HelperFunctions::replaceConstantsFromConfig($config['file_handling']['http_src']);
            $htmlValue = preg_replace_callback(
                '/<a([^>]*)\s+href="[^"]*"([^>]*)\s+data-file-id="([^"]+)"([^>]*)>/i',
                function ($matches) use ($attachmentsMap, $httpSrc) {
                    $fileId = $matches[3];
                    if (isset($attachmentsMap[$fileId])) {
                        $att = $attachmentsMap[$fileId];
                        $newUrl = $httpSrc . '/attachments' . $att['path'] . $att['name'];
                        return '<a' . $matches[1] . ' href="' . htmlspecialchars($newUrl) . '"' . $matches[2] . ' data-file-id="' . $fileId . '"' . $matches[4] . '>';
                    }
                    return $matches[0];
                },
                $htmlValue
            );
        }

        return $htmlValue;
    }
}

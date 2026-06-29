<?php

namespace Pressmind\Image;

use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative as DocumentDerivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class DerivativeCompleteness
{
    /**
     * @param Picture|Section|DocumentMediaObject $entity
     */
    public static function check($entity, array $config, Bucket $bucket, ?array $existingFiles = null): DerivativeCompletenessResult
    {
        $result = new DerivativeCompletenessResult();
        $derivatives = self::getDerivatives($entity);
        $derivativesByName = [];
        foreach ($derivatives as $derivative) {
            $derivativesByName[$derivative->name ?? ''][] = $derivative;
        }

        foreach (($config['image_handling']['processor']['derivatives'] ?? []) as $derivativeName => $derivativeConfig) {
            $matches = $derivativesByName[$derivativeName] ?? [];
            if (count($matches) === 0) {
                $result->missingKeys[] = self::expectedDerivativePrefix($entity, $derivativeName);
                continue;
            }
            if (count($matches) > 1) {
                $result->duplicateDerivativeNames[] = $derivativeName;
            }

            $mainDerivative = $matches[0];
            $mainFileName = $mainDerivative->file_name ?? '';
            if ($mainFileName === '' || !self::fileExists($bucket, $mainFileName, $existingFiles)) {
                $result->missingKeys[] = $mainFileName !== '' ? $mainFileName : self::expectedDerivativePrefix($entity, $derivativeName);
                continue;
            }

	            if (!empty($derivativeConfig['webp_create'])) {
	                $webpFileName = WebpSidecar::fileName($mainFileName);
	                if (!self::webpFileExists($bucket, $webpFileName, $existingFiles)) {
	                    $result->missingKeys[] = $webpFileName;
	                }
	            }
        }

        $result->missingKeys = array_values(array_unique($result->missingKeys));
        $result->duplicateDerivativeNames = array_values(array_unique($result->duplicateDerivativeNames));
        return $result;
    }

    private static function getDerivatives($entity): array
    {
        if (isset($entity->derivatives) && is_array($entity->derivatives)) {
            return $entity->derivatives;
        }
        if (empty($entity->getId())) {
            return [];
        }
        if ($entity instanceof DocumentMediaObject) {
            return DocumentDerivative::listAll(['id_document_media_object' => $entity->getId()]);
        }
        if ($entity instanceof Section) {
            return Derivative::listAll(['id_image_section' => $entity->getId()]);
        }
        return Derivative::listAll(['id_image' => $entity->getId()]);
    }

	    private static function fileExists(Bucket $bucket, string $fileName, ?array $existingFiles = null): bool
	    {
	        if ($existingFiles !== null) {
	            return isset($existingFiles[$fileName]) && (int)$existingFiles[$fileName] > 0;
        }
        $file = new File($bucket);
        $file->name = $fileName;
        if (!$file->exists()) {
            return false;
        }
        try {
            return $file->filesize() > 0;
        } catch (\Exception $e) {
            return false;
	        }
	    }
	
	    private static function webpFileExists(Bucket $bucket, string $fileName, ?array $existingFiles = null): bool
	    {
	        if ($existingFiles !== null && (!isset($existingFiles[$fileName]) || (int)$existingFiles[$fileName] <= 0)) {
	            return false;
	        }
	        return WebpSidecar::isValid($bucket, $fileName);
	    }

    private static function expectedDerivativePrefix($entity, string $derivativeName): string
    {
        return WebpSidecar::derivativePrefix($entity->file_name ?? '') . $derivativeName;
    }
}

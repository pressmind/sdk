<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Image\Processor\Adapter\Factory;
use Pressmind\Image\Processor\Config;
use Pressmind\Image\VerificationStats;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\ORM\Object\ProcessList;
use Pressmind\Registry;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

/**
 * Image Processor Command
 *
 * Downloads and processes images, creates derivatives, and verifies image integrity.
 *
 * Usage:
 *   php cli/image_processor.php [unlock|reset-missing] [mediaobject <id1,id2,...>]
 *
 * Options:
 *   unlock                  Remove the process lock
 *   reset-missing           Set download_successful=0 for all images that are missing (no derivatives on disk), so they are reprocessed on next run
 *   mediaobject <ids>       Process only specific media objects (comma-separated)
 */
class ImageProcessorCommand extends AbstractCommand
{
    private const PROCESS_NAME = 'image_processor';

    private array $config;
    private array $idMediaObjects = [];

    /** @var Bucket|null Shared bucket instance (cached S3 client) for the duration of the run */
    private $bucket = null;

    /**
     * Formats bytes into human-readable size.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        return VerificationStats::formatBytes($bytes, $precision);
    }

    protected function execute(): int
    {
        $this->config = Registry::getInstance()->get('config');

        Writer::write('Image processor started', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        // Handle unlock command
        if ($this->getArgument(0) === 'unlock') {
            return $this->handleUnlock();
        }

        // Handle reset-missing command (no lock required)
        if ($this->getArgument(0) === 'reset-missing') {
            return $this->handleResetMissing();
        }

        // Check for existing lock
        if (!$this->acquireLock()) {
            return 1;
        }

        try {
            $this->bucket = new Bucket($this->config['image_handling']['storage']);
            // Delete original files that are no longer needed
            $this->cleanupOriginalFiles();

            // Process pending images
            $this->processImages();

            // Update MongoDB index if needed
            $this->updateMongoDbIndex();

            // Verify downloaded images (unless skipped)
            if ($this->shouldSkipVerification()) {
                Writer::write('Verification skipped', Writer::OUTPUT_SCREEN, self::PROCESS_NAME, Writer::TYPE_INFO);
            } else {
                $this->verifyImages();
            }

        } finally {
            Writer::write('Image processor finished, removing lock', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
            ProcessList::unlock(self::PROCESS_NAME);
        }

        return 0;
    }

    /**
     * Handles the unlock command.
     */
    private function handleUnlock(): int
    {
        $lock = ProcessList::getLock(self::PROCESS_NAME);
        if ($lock) {
            ProcessList::unlock(self::PROCESS_NAME);
            $this->output->warning('Lock removed! Check if PID ' . $lock->pid . ' exists and kill it with "sudo kill -9 ' . $lock->pid . '" before you run this script again');
        } else {
            $this->output->info('No lock found.');
        }
        return 0;
    }

    /**
     * Acquires the process lock.
     */
    private function acquireLock(): bool
    {
        if (ProcessList::isLocked(self::PROCESS_NAME)) {
            $lock = ProcessList::getLock(self::PROCESS_NAME);
            if (file_exists("/proc/{$lock->pid}")) {
                Writer::write(
                    'is still running, check pid: ' . $lock->pid . ', or try "sudo kill -9 ' . $lock->pid . ' | php image_processor.php unlock"',
                    Writer::OUTPUT_BOTH,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
                return false;
            }
            ProcessList::unlock(self::PROCESS_NAME);
        }
        ProcessList::lock(self::PROCESS_NAME, getmypid());
        return true;
    }

    /**
     * Cleans up original files that are no longer needed.
     * Only deletes an original if at least one derivative exists (prevents data loss if derivatives were never created).
     */
    private function cleanupOriginalFiles(): void
    {
        try {
            $result = array_merge(
                Picture::listAll(['download_successful' => 1]),
                DocumentMediaObject::listAll(['download_successful' => 1])
            );
        } catch (Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            return;
        }

        $count = 0;
        foreach ($result as $image) {
            $file = new File($this->bucket);
            $file->name = $image->file_name;
            if ($file->exists() && !$this->hasWorkToDo($image)) {
                $count++;
                $file->delete();
            }
        }

        if ($count > 0) {
            Writer::write('Deleted ' . $count . ' not used original image files', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
        }
    }

    /**
     * Processes pending images.
     */
    private function processImages(): void
    {
        try {
            $result = array_merge(
                Picture::listAll(['download_successful' => 0]),
                DocumentMediaObject::listAll(['download_successful' => 0])
            );
        } catch (Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            return;
        }

        Writer::write('Processing ' . count($result) . ' images', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        foreach ($result as $image) {
            $this->processSingleImage($image);
        }
    }

    /**
     * Processes a single image.
     *
     * @param Picture|DocumentMediaObject $image
     */
    private function processSingleImage($image): void
    {
        if (!empty($image->id_media_object)) {
            $this->idMediaObjects[] = $image->id_media_object;
        }

        Writer::write('Processing image ID:' . $image->getId(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        // Check if there's anything to do
        if (!$this->hasWorkToDo($image)) {
            Writer::write('Nothing to do (all derivates are created)', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
            if ($image->download_successful == false) {
                $image->download_successful = true;
                $image->update();
                Writer::write('Set download_successful = true (all derivates exist, original was deleted)', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
            }
            return;
        }

        // Download image
        Writer::write('Downloading image from ' . $image->tmp_url, Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
        $binaryImage = $this->downloadImage($image);

        if ($binaryImage === null) {
            Writer::write('Failed to get binary image for ID:' . $image->getId() . ', skipping', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            return;
        }

        // Create derivatives
        $this->createDerivatives($image, $binaryImage);
        unset($binaryImage);

        // Set download_successful only after derivatives were created successfully (prevents stuck state if derivative creation fails)
        $image->download_successful = true;
        $image->update();
    }

    /**
     * Checks if there's work to do for an image.
     *
     * @param Picture|DocumentMediaObject $image
     */
    private function hasWorkToDo($image): bool
    {
        if ($this->bucket->supportsPrefixListing()) {
            $prefix = pathinfo($image->file_name, PATHINFO_FILENAME) . '_';
            $existingFiles = $this->bucket->listByPrefix($prefix);
            foreach ($this->config['image_handling']['processor']['derivatives'] as $derivativeName => $derivativeConfig) {
                $extensions = ['jpg'];
                if (!empty($derivativeConfig['webp_create'])) {
                    $extensions[] = 'webp';
                }
                foreach ($extensions as $extension) {
                    $expectedKey = $prefix . $derivativeName . '.' . $extension;
                    if (!isset($existingFiles[$expectedKey])) {
                        return true;
                    }
                }
            }
            if (!empty($image->sections) && is_array($image->sections)) {
                foreach ($image->sections as $section) {
                    if ($this->sectionHasWorkToDo($section)) {
                        return true;
                    }
                }
            }
            return false;
        }

        foreach ($this->config['image_handling']['processor']['derivatives'] as $derivativeName => $derivativeConfig) {
            $extensions = ['jpg'];
            if (!empty($derivativeConfig['webp_create'])) {
                $extensions[] = 'webp';
            }
            foreach ($extensions as $extension) {
                $file = new File($this->bucket);
                $file->name = pathinfo($image->file_name, PATHINFO_FILENAME) . '_' . $derivativeName . '.' . $extension;
                if (!$file->exists()) {
                    return true;
                }
                if (!empty($image->sections) && is_array($image->sections)) {
                    foreach ($image->sections as $section) {
                        $sectionFile = new File($this->bucket);
                        $sectionFile->name = pathinfo($section->file_name, PATHINFO_FILENAME) . '_' . $derivativeName . '.' . $extension;
                        if (!$sectionFile->exists()) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Downloads an image.
     *
     * @param Picture|DocumentMediaObject $image
     * @return File|null Storage file object or null on failure
     */
    private function downloadImage($image): ?File
    {
        try {
            if ($image->exists()) {
                Writer::write('File exists (' . $image->file_name . '), no download required', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
                return $image->getBinaryFile();
            }
            return $image->downloadOriginal();
        } catch (Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            return null;
        }
    }

    /**
     * Creates derivatives for an image.
     *
     * @param Picture|DocumentMediaObject $image
     * @param File $binaryImage
     */
    private function createDerivatives($image, File $binaryImage): void
    {
        $imageProcessor = Factory::create($this->config['image_handling']['processor']['adapter']);
        Writer::write('Creating derivatives (Adapter: ' . $this->config['image_handling']['processor']['adapter'] . ')', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        foreach ($this->config['image_handling']['processor']['derivatives'] as $derivativeName => $derivativeConfig) {
            try {
                Writer::write('Creating derivative: ' . $derivativeName, Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
                $processorConfig = Config::create($derivativeName, $derivativeConfig);
                $image->createDerivative($processorConfig, $imageProcessor, $binaryImage);
                Writer::write('Processing sections', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
            } catch (Exception $e) {
                Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
                continue;
            }

            // Process sections
            if (!empty($image->sections) && is_array($image->sections)) {
                $this->processSections($image->sections, $processorConfig, $imageProcessor);
            }
        }
    }

    /**
     * Processes image sections.
     *
     * @param array $sections
     * @param Config $processorConfig
     * @param mixed $imageProcessor
     */
    private function processSections(array $sections, Config $processorConfig, $imageProcessor): void
    {
        foreach ($sections as $section) {
            // Check if all section derivatives exist
            if (!$this->sectionHasWorkToDo($section)) {
                if ($section->download_successful == false) {
                    $section->download_successful = true;
                    $section->update();
                    Writer::write('Set section download_successful = true (all derivates exist)', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
                }
                continue;
            }

            Writer::write('Downloading section image from ' . $section->tmp_url, Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
            try {
                $binarySectionFile = $this->downloadSection($section);

                if ($binarySectionFile === null) {
                    Writer::write('Failed to get binary section file for ID:' . $section->getId() . ', skipping', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
                    continue;
                }

                Writer::write('Creating section image derivatives', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
                $section->createDerivative($processorConfig, $imageProcessor, $binarySectionFile);
                unset($binarySectionFile);
            } catch (Exception $e) {
                Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }
    }

    /**
     * Checks if a section has work to do.
     */
    private function sectionHasWorkToDo(Section $section): bool
    {
        if ($this->bucket->supportsPrefixListing()) {
            $prefix = pathinfo($section->file_name, PATHINFO_FILENAME) . '_';
            $existingFiles = $this->bucket->listByPrefix($prefix);
            foreach ($this->config['image_handling']['processor']['derivatives'] as $derivativeName => $derivativeConfig) {
                $extensions = ['jpg'];
                if (!empty($derivativeConfig['webp_create'])) {
                    $extensions[] = 'webp';
                }
                foreach ($extensions as $extension) {
                    $expectedKey = $prefix . $derivativeName . '.' . $extension;
                    if (!isset($existingFiles[$expectedKey])) {
                        return true;
                    }
                }
            }
            return false;
        }

        foreach ($this->config['image_handling']['processor']['derivatives'] as $derivativeName => $derivativeConfig) {
            $extensions = ['jpg'];
            if (!empty($derivativeConfig['webp_create'])) {
                $extensions[] = 'webp';
            }
            foreach ($extensions as $extension) {
                $sectionFile = new File($this->bucket);
                $sectionFile->name = pathinfo($section->file_name, PATHINFO_FILENAME) . '_' . $derivativeName . '.' . $extension;
                if (!$sectionFile->exists()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Downloads a section image.
     *
     * @return File|null
     */
    private function downloadSection(Section $section): ?File
    {
        if ($section->exists()) {
            $section->download_successful = true;
            $section->update();
            Writer::write('Section file exists (' . $section->file_name . '), no download required', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
            return $section->getBinaryFile();
        }
        return $section->downloadOriginal();
    }

    /**
     * Updates MongoDB index if new images were processed.
     */
    private function updateMongoDbIndex(): void
    {
        if (empty($this->idMediaObjects)) {
            return;
        }

        Writer::write('Update MongoDB Index (new urls are present)', Writer::OUTPUT_SCREEN, self::PROCESS_NAME, Writer::TYPE_INFO);
        $this->idMediaObjects = array_unique($this->idMediaObjects);
        $indexer = new Indexer();
        $indexer->upsertMediaObject($this->idMediaObjects);
        Writer::write('updated', Writer::OUTPUT_SCREEN, self::PROCESS_NAME, Writer::TYPE_INFO);
    }

    /**
     * Whether verification should be skipped (--skip-verification or argument skip-verification).
     */
    private function shouldSkipVerification(): bool
    {
        return $this->hasOption('skip-verification')
            || $this->getArgument(0) === 'skip-verification'
            || $this->getArgument(1) === 'skip-verification';
    }

    /**
     * Runs verification and returns statistics (without output).
     * Uses streaming scan when bucket supports it (large buckets); otherwise chunked processing.
     *
     * @return array Verification stats with keys pictures, sections, documents; each has total, exists, missing, missing_list, derivatives (empty when chunked); plus derivative_summary
     */
    private function runVerificationAndGetStats(): array
    {
        $startTime = microtime(true);
        $progressCallback = function (int $keysProcessed, bool $isFinal = false) use ($startTime) {
            if ($isFinal) {
                $durationMinutes = round((microtime(true) - $startTime) / 60, 1);
                Writer::write(
                    'Bucket scan complete: ' . number_format($keysProcessed, 0, ',', '.') . ' keys processed in ' . $durationMinutes . ' minutes',
                    Writer::OUTPUT_SCREEN,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
            } else {
                Writer::write(
                    'Scanning bucket: ' . number_format($keysProcessed, 0, ',', '.') . ' keys processed...',
                    Writer::OUTPUT_SCREEN,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
            }
        };

        return VerificationStats::collect($this->config, [
            'chunk_size' => VerificationStats::DEFAULT_CHUNK_SIZE,
            'max_missing_list' => VerificationStats::DEFAULT_MAX_MISSING_LIST,
            'progress_callback' => $progressCallback,
        ]);
    }

    /**
     * Verifies downloaded images and outputs statistics.
     * Automatically resets download_successful=0 for missing images so they are reprocessed on the next run.
     */
    private function verifyImages(): void
    {
        Writer::write('Starting image verification...', Writer::OUTPUT_SCREEN, self::PROCESS_NAME, Writer::TYPE_INFO);
        $verificationStats = $this->runVerificationAndGetStats();
        $resetCount = $this->resetMissingImagesInStats($verificationStats);
        if ($resetCount > 0) {
            Writer::write('Recovery: set download_successful=0 for ' . $resetCount . ' missing image(s). They will be reprocessed on the next run.', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
        }
        $this->outputVerificationReport($verificationStats);
    }

    /**
     * Resets download_successful to 0 for all entries in the given stats' missing_list.
     * Returns the number of records reset.
     */
    private function resetMissingImagesInStats(array $stats): int
    {
        $resetCount = 0;
        foreach ($stats['pictures']['missing_list'] as $item) {
            try {
                $picture = new Picture();
                $picture->read($item['id']);
                $picture->download_successful = false;
                $picture->update();
                $resetCount++;
            } catch (Exception $e) {
                Writer::write('Failed to reset picture ID ' . $item['id'] . ': ' . $e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }
        foreach ($stats['sections']['missing_list'] as $item) {
            try {
                $section = new Section();
                $section->read($item['id']);
                $section->download_successful = false;
                $section->update();
                $resetCount++;
            } catch (Exception $e) {
                Writer::write('Failed to reset section ID ' . $item['id'] . ': ' . $e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }
        foreach ($stats['documents']['missing_list'] as $item) {
            try {
                $document = new DocumentMediaObject();
                $document->read($item['id']);
                $document->download_successful = false;
                $document->update();
                $resetCount++;
            } catch (Exception $e) {
                Writer::write('Failed to reset document media object ID ' . $item['id'] . ': ' . $e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }
        return $resetCount;
    }

    /**
     * Resets download_successful to 0 for all images that are missing (no derivatives on disk).
     * Runs in a loop until no missing images remain (handles capped missing_list with many missing).
     * Then run the image processor to reprocess them.
     */
    private function handleResetMissing(): int
    {
        $this->config = Registry::getInstance()->get('config');
        $maxIterations = 10000;
        $totalReset = 0;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            Writer::write('Collecting missing images (pass ' . $iteration . ')...', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);
            $stats = $this->runVerificationAndGetStats();
            $totalMissing = $stats['pictures']['missing'] + $stats['sections']['missing'] + $stats['documents']['missing'];

            if ($totalMissing === 0) {
                if ($totalReset === 0) {
                    $this->output->info('No missing images found. Nothing to reset.');
                } else {
                    $this->output->info('All missing images reset. Total: ' . $totalReset . ' record(s). Run "php image_processor.php" to reprocess them.');
                }
                return 0;
            }

            $resetCount = $this->resetMissingImagesInStats($stats);
            $totalReset += $resetCount;
            $this->output->info('Pass ' . $iteration . ': reset ' . $resetCount . ' missing image(s). Total reset so far: ' . $totalReset . '.');

            if ($resetCount === 0) {
                $this->output->warning('No records were reset (remaining ' . $totalMissing . ' missing could not be updated).');
                return 0;
            }
        }

        $this->output->warning('Stopped after ' . $maxIterations . ' passes. Total reset: ' . $totalReset . '. Run again to continue or run image processor.');
        return 0;
    }

    /**
     * Outputs the verification report.
     */
    private function outputVerificationReport(array $stats): void
    {
        $totalChecked = $stats['pictures']['total'] + $stats['sections']['total'] + $stats['documents']['total'];
        $totalExists = $stats['pictures']['exists'] + $stats['sections']['exists'] + $stats['documents']['exists'];
        $totalMissing = $stats['pictures']['missing'] + $stats['sections']['missing'] + $stats['documents']['missing'];

        $tableWidth = 140;
        $titleBorder = str_repeat('═', $tableWidth - 2);
        $borderLine = str_repeat('─', $tableWidth - 2);

        echo "\n";
        echo "╔" . $titleBorder . "╗\n";
        echo "║" . str_pad("IMAGE VERIFICATION REPORT", $tableWidth - 2, ' ', STR_PAD_BOTH) . "║\n";
        echo "╚" . $titleBorder . "╝\n";
        echo "\n";

        // Summary statistics
        echo "┌" . $borderLine . "┐\n";
        echo "│" . str_pad("SUMMARY", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
        echo "├" . $borderLine . "┤\n";

        $line = str_pad("Checked images:", 25, ' ') . str_pad(number_format($totalChecked, 0, ',', '.'), 15, ' ', STR_PAD_LEFT);
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";

        $percentage = $totalChecked > 0 ? round(($totalExists / $totalChecked) * 100, 2) : 0;
        $line = str_pad("Existing:", 25, ' ') . str_pad(number_format($totalExists, 0, ',', '.') . " (" . $percentage . "%)", 15, ' ', STR_PAD_LEFT);
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";

        $percentage = $totalChecked > 0 ? round(($totalMissing / $totalChecked) * 100, 2) : 0;
        $line = str_pad("Missing:", 25, ' ') . str_pad(number_format($totalMissing, 0, ',', '.') . " (" . $percentage . "%)", 15, ' ', STR_PAD_LEFT);
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";
        echo "└" . $borderLine . "┘\n";
        echo "\n";

        // Detailed statistics by type
        echo "┌" . $borderLine . "┐\n";
        echo "│" . str_pad("DETAILED STATISTICS", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
        echo "├" . $borderLine . "┤\n";

        $line = str_pad("Pictures:", 25, ' ') . str_pad(number_format($stats['pictures']['total'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " checked | " . str_pad(number_format($stats['pictures']['exists'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " existing | " . str_pad(number_format($stats['pictures']['missing'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " missing";
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";

        $line = str_pad("Sections:", 25, ' ') . str_pad(number_format($stats['sections']['total'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " checked | " . str_pad(number_format($stats['sections']['exists'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " existing | " . str_pad(number_format($stats['sections']['missing'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " missing";
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";

        $line = str_pad("DocumentMediaObjects:", 25, ' ') . str_pad(number_format($stats['documents']['total'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " checked | " . str_pad(number_format($stats['documents']['exists'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " existing | " . str_pad(number_format($stats['documents']['missing'], 0, ',', '.'), 12, ' ', STR_PAD_LEFT) . " missing";
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";
        echo "└" . $borderLine . "┘\n";
        echo "\n";

        // Derivative summary
        $this->outputDerivativeSummary($stats, $tableWidth, $borderLine);

        // Missing images
        $this->outputMissingImages($stats, $totalMissing, $tableWidth, $borderLine);

        // Total size
        $this->outputTotalSize($stats, $tableWidth, $borderLine);
    }

    /**
     * Outputs derivative summary.
     * Uses pre-aggregated derivative_summary when present (chunked mode); otherwise builds from derivatives arrays.
     */
    private function outputDerivativeSummary(array $stats, int $tableWidth, string $borderLine): void
    {
        echo "┌" . $borderLine . "┐\n";
        echo "│" . str_pad("DERIVATIVE SUMMARY", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
        echo "├" . $borderLine . "┤\n";

        $derivativeSummary = isset($stats['derivative_summary']) && is_array($stats['derivative_summary'])
            ? $stats['derivative_summary']
            : $this->buildDerivativeSummaryFromStats($stats);

        // Output summary
        $currentType = '';
        foreach ($derivativeSummary as $summary) {
            if ($currentType !== $summary['type']) {
                if ($currentType !== '') {
                    echo "│" . str_pad('', $tableWidth - 2, ' ') . "│\n";
                }
                $typeLine = " " . strtoupper($summary['type']) . ":";
                echo "│" . str_pad($typeLine, $tableWidth - 2, ' ') . "│\n";
                echo "├" . $borderLine . "┤\n";
                $currentType = $summary['type'];
            }

            $avgSize = $summary['exists_count'] > 0 ? $summary['total_size'] / $summary['exists_count'] : 0;
            $percentage = $summary['total_count'] > 0 ? round(($summary['exists_count'] / $summary['total_count']) * 100, 1) : 0;

            $line = "   " . str_pad($summary['name'], 25, ' ') . "." . str_pad($summary['extension'], 5, ' ') . " | " .
                str_pad(number_format($summary['exists_count'], 0, ',', '.'), 8, ' ', STR_PAD_LEFT) . "/" .
                str_pad(number_format($summary['total_count'], 0, ',', '.'), 8, ' ', STR_PAD_LEFT) . " existing (" .
                str_pad(number_format($percentage, 1), 6, ' ', STR_PAD_LEFT) . "%) | Total: " .
                str_pad(VerificationStats::formatBytes($summary['total_size']), 15, ' ') . " | Average: " .
                str_pad(VerificationStats::formatBytes((int)$avgSize), 15, ' ');
            echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";
        }

        if (count($derivativeSummary) == 0) {
            echo "│" . str_pad("No derivatives found", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
        }

        echo "└" . $borderLine . "┘\n";
        echo "\n";
    }

    /**
     * Builds derivative summary from full derivatives arrays (used when not in chunked mode).
     *
     * @return list<array{name: string, extension: string, total_count: int, exists_count: int, total_size: int, type: string}>
     */
    private function buildDerivativeSummaryFromStats(array $stats): array
    {
        $derivativeSummary = [];
        foreach (['pictures' => 'Pictures', 'sections' => 'Sections', 'documents' => 'Documents'] as $type => $typeName) {
            foreach ($stats[$type]['derivatives'] as $data) {
                foreach ($data['derivatives'] as $derivative) {
                    $key = $derivative['name'] . '.' . $derivative['extension'] . '.' . $type;
                    if (!isset($derivativeSummary[$key])) {
                        $derivativeSummary[$key] = [
                            'name' => $derivative['name'],
                            'extension' => $derivative['extension'],
                            'total_count' => 0,
                            'exists_count' => 0,
                            'total_size' => 0,
                            'type' => $typeName
                        ];
                    }
                    $derivativeSummary[$key]['total_count']++;
                    if ($derivative['exists']) {
                        $derivativeSummary[$key]['exists_count']++;
                        $derivativeSummary[$key]['total_size'] += $derivative['size'];
                    }
                }
            }
        }
        $list = array_values($derivativeSummary);
        usort($list, function ($a, $b) {
            $typeOrder = ['Pictures' => 1, 'Sections' => 2, 'Documents' => 3];
            $typeCmp = ($typeOrder[$a['type']] ?? 0) <=> ($typeOrder[$b['type']] ?? 0);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }
            return strcmp($a['name'], $b['name']);
        });
        return $list;
    }

    /**
     * Outputs missing images list.
     */
    private function outputMissingImages(array $stats, int $totalMissing, int $tableWidth, string $borderLine): void
    {
        if ($totalMissing > 0) {
            echo "┌" . $borderLine . "┐\n";
            echo "│" . str_pad("MISSING IMAGES", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
            echo "├" . $borderLine . "┤\n";

            foreach (['pictures' => 'PICTURES', 'sections' => 'SECTIONS', 'documents' => 'DOCUMENT MEDIA OBJECTS'] as $key => $label) {
                if (count($stats[$key]['missing_list']) > 0) {
                    echo "│" . str_pad('', $tableWidth - 2, ' ') . "│\n";
                    $header = $label . " (" . count($stats[$key]['missing_list']) . " missing):";
                    echo "│" . str_pad($header, $tableWidth - 2, ' ') . "│\n";
                    echo "├" . $borderLine . "┤\n";

                    foreach ($stats[$key]['missing_list'] as $item) {
                        $idStr = "ID: " . $item['id'];
                        $fileStr = substr($item['file_name'], 0, 70);
                        if (strlen($item['file_name']) > 70) {
                            $fileStr .= "...";
                        }

                        if ($key === 'sections') {
                            $line = str_pad($idStr, 12, ' ') . " | MediaObject: " . str_pad($item['id_media_object'], 10, ' ') . " | Section: " . str_pad($item['section_name'], 20, ' ') . " | " . str_pad($fileStr, 70, ' ');
                        } elseif ($key === 'documents') {
                            $line = str_pad($idStr, 12, ' ') . " | MediaObject: " . str_pad($item['id_media_object'], 10, ' ') . " | Step: " . str_pad($item['id_step'], 10, ' ') . " | " . str_pad($fileStr, 70, ' ');
                        } else {
                            $line = str_pad($idStr, 12, ' ') . " | MediaObject: " . str_pad($item['id_media_object'], 10, ' ') . " | " . str_pad($fileStr, 80, ' ');
                        }
                        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";
                    }
                }
            }
            echo "└" . $borderLine . "┘\n";
        } else {
            echo "┌" . $borderLine . "┐\n";
            echo "│" . str_pad("ALL IMAGES SUCCESSFULLY DOWNLOADED AND PRESENT", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
            echo "└" . $borderLine . "┘\n";
        }
        echo "\n";
    }

    /**
     * Outputs total file size.
     * When chunked verification is used, derivatives arrays are empty; total is taken from derivative_summary.
     */
    private function outputTotalSize(array $stats, int $tableWidth, string $borderLine): void
    {
        $totalFileSize = 0;

        $hasDerivatives = !empty($stats['pictures']['derivatives']) || !empty($stats['sections']['derivatives']) || !empty($stats['documents']['derivatives']);
        if ($hasDerivatives) {
            foreach (['pictures', 'sections', 'documents'] as $type) {
                foreach ($stats[$type]['derivatives'] as $data) {
                    foreach ($data['derivatives'] as $derivative) {
                        $totalFileSize += $derivative['size'];
                    }
                }
            }
        } else {
            foreach ($stats['derivative_summary'] ?? [] as $summary) {
                $totalFileSize += $summary['total_size'] ?? 0;
            }
        }

        echo "┌" . $borderLine . "┐\n";
        echo "│" . str_pad("TOTAL SIZE OF ALL DERIVATIVES", $tableWidth - 2, ' ', STR_PAD_BOTH) . "│\n";
        echo "├" . $borderLine . "┤\n";
        $line = str_pad("Total size:", 25, ' ') . str_pad(VerificationStats::formatBytes($totalFileSize), 15, ' ', STR_PAD_LEFT);
        echo "│" . str_pad($line, $tableWidth - 2, ' ') . "│\n";
        echo "└" . $borderLine . "┘\n";
        echo "\n";
    }
}

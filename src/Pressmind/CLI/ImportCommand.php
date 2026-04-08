<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Import;
use Pressmind\Import\CategoryTree;
use Pressmind\Registry;
use Pressmind\Import\Itinerary as ItineraryImporter;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\ProcessList;
use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\REST\Controller\System;

/**
 * Import CLI Command
 *
 * Orchestrates all import subcommands (fullimport, mediaobject, touristic, etc.).
 * Lock handling, config (-c=), and optional post-import callback (e.g. Redis cache) are supported.
 *
 * @see wp-travelshop-theme/cli/import.php (wrapper)
 */
class ImportCommand extends AbstractCommand
{
    private ?string $scriptPath = null;

    /** @var callable|null callback(array $importedIds, ?Import $importer): void */
    private $onAfterImportCallback = null;

    public function setOnAfterImportCallback(callable $callback): void
    {
        $this->onAfterImportCallback = $callback;
    }

    public function run(array $argv): int
    {
        $this->scriptPath = $argv[0] ?? '';
        return parent::run($argv);
    }

    protected function parseArguments(array $argv): void
    {
        parent::parseArguments($argv);
        $fixed = [];
        foreach ($this->options as $key => $value) {
            if (str_contains((string) $key, '=')) {
                [$k, $v] = explode('=', $key, 2);
                $fixed[$k] = $v;
            } else {
                $fixed[$key] = $value;
            }
        }
        $this->options = $fixed;
    }

    protected function execute(): int
    {
        $locked = false;
        try {
            $this->applyConfig();
            $this->applyDebug();

            $subcommand = $this->getArgument(0);

            if ($subcommand === 'unlock') {
                $this->handleUnlock();
                return 0;
            }

            if ($this->handleStaleLock()) {
                return 1;
            }
            ProcessList::lock('import', getmypid());
            $locked = true;

            if (in_array($subcommand, ['help', '--help', '-h'], true) || $subcommand === null) {
                $this->printHelp();
                return 0;
            }

            return $this->dispatchSubcommand($subcommand);
        } finally {
            if ($locked) {
                Writer::write('Import finished, removing lock', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
                ProcessList::unlock('import');
            }
        }
    }

    private function applyConfig(): void
    {
        $configFile = $this->getOption('c') ?? $this->getOption('config');
        if ($configFile === null || $configFile === true) {
            return;
        }
        $configFile = trim((string) $configFile);
        $baseDir = $this->scriptPath !== '' ? dirname($this->scriptPath) : getcwd();
        $fullPath = $baseDir . '/../' . $configFile;
        if (!file_exists($fullPath)) {
            $this->output->error('error: file does not exist ' . $fullPath);
            exit(1);
        }
        putenv('PM_CONFIG=' . $configFile);
        $this->output->writeln('pm-config loaded: ' . getenv('PM_CONFIG'), null);
    }

    private function applyDebug(): void
    {
        if (in_array('debug', $this->arguments, true) && !defined('PM_SDK_DEBUG')) {
            define('PM_SDK_DEBUG', true);
        }
    }

    private function handleUnlock(): void
    {
        if (!ProcessList::getLock('import')) {
            return;
        }
        $lock = ProcessList::getLock('import');
        ProcessList::unlock('import');
        $this->output->writeln('WARNING: lock removed! Check if PID ' . $lock->pid . ' exists and kill it with "sudo kill -9 ' . $lock->pid . '" - before you run this script again', null);
    }

    /**
     * Checks for a stale lock. Returns true if the import is still running and we should abort.
     */
    private function handleStaleLock(): bool
    {
        if (!ProcessList::isLocked('import')) {
            return false;
        }
        $lock = ProcessList::getLock('import');
        if (file_exists("/proc/{$lock->pid}")) {
            $this->output->error('Import is still running (pid: ' . $lock->pid . '). Try "sudo kill -9 ' . $lock->pid . '" and then "php import.php unlock" before running again.');
            Writer::write('Import is still running, check pid: ' . $lock->pid, Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
            return true;
        }
        ProcessList::unlock('import');
        return false;
    }

    private function dispatchSubcommand(string $subcommand): int
    {
        $idsArg = $this->getArgument(1);
        $ids = $idsArg !== null && $idsArg !== '' ? array_map('trim', explode(',', (string) $idsArg)) : [];

        $logPath = Writer::getLogFilePath() . DIRECTORY_SEPARATOR . 'import_error.log';

        switch ($subcommand) {
            case 'fullimport':
                return $this->subFullimport($logPath);
            case 'sync':
                return $this->subSync($logPath);
            case 'resume':
                return $this->subResume($logPath);
            case 'mediaobject':
                return $this->subMediaobject($ids, $logPath);
            case 'mediaobject_cache_update':
                return $this->subMediaobjectCacheUpdate($ids, $logPath);
            case 'itinerary':
                return $this->subItinerary($ids, $logPath);
            case 'objecttypes':
                return $this->subObjecttypes($ids, $logPath);
            case 'depublish':
                return $this->subDepublish($ids, $logPath);
            case 'destroy':
                return $this->subDestroy($ids, $logPath);
            case 'remove_orphans':
                return $this->subRemoveOrphans($logPath);
            case 'update_tags':
                return $this->subUpdateTags($logPath);
            case 'offer':
                return $this->subOffer($ids, $logPath);
            case 'powerfilter':
                return $this->subPowerfilter($logPath);
            case 'calendar':
                return $this->subCalendar($ids, $logPath);
            case 'postimport':
                return $this->subPostimport($ids, $logPath);
            case 'categories':
                return $this->subCategories($ids, $logPath);
            case 'create_translations':
                return $this->subCreateTranslations($logPath);
            case 'reset_insurances':
                return $this->subResetInsurances($logPath);
            case 'dedupe_insurance_relations':
                return $this->subDedupeInsuranceRelations($logPath);
            case 'touristic':
                return $this->subTouristic($ids, $logPath);
            case 'fullimport_touristic':
                return $this->subFullimportTouristic($logPath);
            case 'filestorage':
                return $this->subFilestorage($logPath);
            default:
                $this->printHelp();
                return 0;
        }
    }

    private function invokeAfterImportCallback(array $ids, ?Import $importer = null): void
    {
        if ($this->onAfterImportCallback !== null && !empty($ids)) {
            ($this->onAfterImportCallback)($ids, $importer);
        }
    }

    private function reportErrors(Import $importer, string $logPath): void
    {
        if (!$importer->hasErrors()) {
            return;
        }
        $this->output->writeln($importer->getGroupedErrorSummary(), null);
        $config = Registry::getInstance()->get('config');
        if (isset($config['logging']['storage']) && $config['logging']['storage'] === 'database') {
            $this->output->writeln('Details: See database log table (pmt2core_logs)', null);
        } else {
            $this->output->writeln('Details: ' . $logPath, null);
        }
    }

    private function logImportException(Exception $e, string $logPath, string $context = 'Import threw errors'): void
    {
        Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_ERROR);
        $this->output->error("WARNING: {$context}:\n" . $e->getMessage() . "\nSEE " . $logPath . " for details");
    }

    private function subFilestorage(string $logPath): int
    {
        $force = $this->hasOption('force');
        $folderOpt = $this->getOption('folder');
        $rootFolder = ($folderOpt !== null && $folderOpt !== true && $folderOpt !== '') ? (string) $folderOpt : null;
        $removeOrphans = $rootFolder === null;
        $noDownload = $this->hasOption('no-download');

        $importer = null;
        try {
            $importer = new \Pressmind\Import\FileStorage($force, $rootFolder, $removeOrphans);
            $importer->import();
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'FileStorage import failed');
            return 1;
        }
        if ($importer !== null) {
            $this->reportFileStorageSideErrors($importer, $logPath);
        }

        if (!$noDownload) {
            $dl = new AttachmentDownloaderCommand();
            $dl->run(['import.php', 'filestorage']);
        }

        return 0;
    }

    private function reportFileStorageSideErrors(\Pressmind\Import\FileStorage $importer, string $logPath): void
    {
        if (count($importer->getErrors()) === 0) {
            return;
        }
        foreach ($importer->getErrors() as $err) {
            Writer::write((string) $err, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_ERROR);
        }
        $this->output->writeln('FileStorage reported errors; see ' . $logPath, null);
    }

    private function subFullimport(string $logPath): int
    {
        $importer = new Import('fullimport');
        try {
            $importer->import();
            if ($importer->isResuming()) {
                Writer::write('Resumed previous fullimport', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } else {
                Writer::write('Importing all media objects', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            }
            $this->reportErrors($importer, $logPath);
            Writer::write('Import done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        } finally {
            $importer->postImport();
            $ids = $importer->getImportedIds();
            $this->invokeAfterImportCallback($ids, $importer);
            $this->output->writeln(implode(', ', $ids), null);
        }
        if ($this->hasOption('validate')) {
            foreach ($ids as $id) {
                $this->output->writeln('===== Validation =====', null);
                $mediaObject = new MediaObject($id);
                $this->output->writeln(implode("\n", $mediaObject->validate()), null);
            }
        }
        return 0;
    }

    private function subSync(string $logPath): int
    {
        $importer = new Import('sync');
        try {
            $importer->import();
            if ($importer->isResuming()) {
                Writer::write('Resumed previous sync', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } else {
                Writer::write('Syncing all media objects (hash-based delta)', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            }
            $this->reportErrors($importer, $logPath);
            Writer::write('Sync done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        } finally {
            $importer->postImport();
            $ids = $importer->getImportedIds();
            $this->invokeAfterImportCallback($ids, $importer);
            $this->output->writeln(implode(', ', $ids), null);
        }
        if ($this->hasOption('validate')) {
            foreach ($ids as $id) {
                $this->output->writeln('===== Validation =====', null);
                $mediaObject = new MediaObject($id);
                $this->output->writeln(implode("\n", $mediaObject->validate()), null);
            }
        }
        return 0;
    }

    private function subResume(string $logPath): int
    {
        $importer = new Import('resume');
        try {
            $ids = $importer->processQueue();
            if (empty($ids)) {
                return 0;
            }
            $this->reportErrors($importer, $logPath);
            Writer::write('Import done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        } finally {
            $importer->postImport();
            $ids = $importer->getImportedIds();
            $this->invokeAfterImportCallback($ids, $importer);
            $this->output->writeln(implode(', ', $ids), null);
        }
        return 0;
    }

    private function subMediaobject(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            $this->output->writeln('Missing mediaobject id(s)', null);
            return 1;
        }
        $force = $this->hasOption('force');
        $importer = new Import('mediaobject');
        Writer::write('Importing mediaobject ID(s): ' . implode(',', $ids) . ($force ? ' (--force)' : ''), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer->importMediaObjectsFromArray($ids, true, $force);
            Writer::write('Import done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $importer->postImport($ids);
            $this->reportErrors($importer, $logPath);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        $importedIds = $importer->getImportedIds();
        $this->invokeAfterImportCallback($importedIds, $importer);
        if (!$this->hasOption('no-validate')) {
            foreach ($importedIds as $id) {
                $this->output->writeln('===== Validation =====', null);
                $mediaObject = new MediaObject($id);
                $r = $mediaObject->validate();
                $this->output->writeln(implode("\n", $r), null);
            }
        }
        Writer::write(
            'Hints: Use --force to bypass hash comparison and force a full re-import. '
            . 'Use "php image_processor.php --report" to generate the image verification report (skipped by default).',
            Writer::OUTPUT_SCREEN,
            'import',
            Writer::TYPE_INFO
        );
        return 0;
    }

    private function subMediaobjectCacheUpdate(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            $this->output->writeln('Missing mediaobject id(s)', null);
            return 1;
        }
        Writer::write('Importing mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        $this->invokeAfterImportCallback($ids);
        return 0;
    }

    private function subItinerary(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            $this->output->writeln('Missing mediaobject id(s)', null);
            return 1;
        }
        Writer::write('Importing itinerary for Media Object ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            foreach ($ids as $id) {
                $itineraryImporter = new ItineraryImporter($id);
                $itineraryImporter->import();
            }
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        return 0;
    }

    private function subObjecttypes(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            $this->output->writeln('Missing objecttype id(s)', null);
            return 1;
        }
        $importer = new Import('objecttypes');
        Writer::write('Importing objecttypes ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer->importMediaObjectTypes($ids);
            Writer::write('Import done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $this->reportErrors($importer, $logPath);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        return 0;
    }

    private function subDepublish(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            return 0;
        }
        Writer::write('Depublishing mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        foreach ($ids as $id) {
            try {
                $mediaObject = new MediaObject($id);
                $mediaObject->visibility = 10;
                $mediaObject->update();
                $mediaObject->createMongoDBIndex();
                Writer::write('Mediaobject ' . $id . ' successfully depublished (visibility set to 10/nobody)', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } catch (Exception $e) {
                $this->logImportException($e, $logPath, 'Depublish for id ' . $id . ' failed');
            }
        }
        return 0;
    }

    private function subDestroy(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            return 0;
        }
        Writer::write('Destroying mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        foreach ($ids as $id) {
            try {
                $mediaObject = new MediaObject($id);
                $mediaObject->delete(true);
                Writer::write('Mediaobject ' . $id . ' successfully destroyed', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } catch (Exception $e) {
                $this->logImportException($e, $logPath, 'Destruction for mediaobject ' . $id . ' failed');
            }
        }
        return 0;
    }

    private function subRemoveOrphans(string $logPath): int
    {
        $importer = new Import('remove_orphans');
        Writer::write('Removing orphans from database', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer->removeOrphans();
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        return 0;
    }

    private function subUpdateTags(string $logPath): int
    {
        $idObjectType = $this->getArgument(1);
        $system = new System();
        try {
            $system->updateTags(['id_object_type' => $idObjectType]);
            Writer::write('updating tags done', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        return 0;
    }

    private function subOffer(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            return 0;
        }
        Writer::write('Regenerate offers for mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        foreach ($ids as $id) {
            try {
                $mediaObject = new MediaObject($id);
                $mediaObject->insertCheapestPrice();
                $mediaObject->createMongoDBIndex();
                Writer::write('Mediaobject offers (cheapestPrices) for ' . $id . ' successfully regenerated', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } catch (Exception $e) {
                $this->logImportException($e, $logPath, 'Regenerate offers for id ' . $id . ' failed');
            }
        }
        return 0;
    }

    private function subPowerfilter(string $logPath): int
    {
        Writer::write('Import Powerfilter and ResultSets', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $powerfilterImporter = new Import\Powerfilter();
            $powerfilterImporter->import();
            $indexer = new \Pressmind\Search\MongoDB\Indexer();
            $indexer->upsertPowerfilter();
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'Powerfilter Import failed');
            return 1;
        }
        return 0;
    }

    private function subCalendar(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            return 0;
        }
        Writer::write('Regenerate calendars for mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        foreach ($ids as $id) {
            try {
                $mediaObject = new MediaObject($id);
                $mediaObject->insertCheapestPrice();
                $mediaObject->createMongoDBCalendar();
                Writer::write('Mediaobject calendars for ' . $id . ' successfully regenerated', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } catch (Exception $e) {
                $this->logImportException($e, $logPath, 'Regenerate calendars for id ' . $id . ' failed');
            }
        }
        return 0;
    }

    private function subPostimport(array $ids, string $logPath): int
    {
        Writer::write('Running post import', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer = new Import('fullimport');
            $importer->postImport($ids);
            Writer::write('Post import completed (image generation is running in background perhaps', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'post import failed');
            return 1;
        }
        return 0;
    }

    private function subCategories(array $ids, string $logPath): int
    {
        Writer::write('Import CategoryTrees', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $categoryImporter = new CategoryTree($ids);
            $categoryImporter->import();
            Writer::write('Import completed', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'CategoryTree import failed');
            return 1;
        }
        return 0;
    }

    private function subCreateTranslations(string $logPath): int
    {
        Writer::write('Create Translations', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $categoryImporter = new CategoryTree([]);
            $categoryImporter->createGetTextFiles();
            foreach ($categoryImporter->getLog() as $log) {
                Writer::write($log, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            }
            foreach ($categoryImporter->getErrors() as $error) {
                Writer::write($error, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_ERROR);
            }
            Writer::write('Completed', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'Create Translations failed');
            return 1;
        }
        return 0;
    }

    private function subResetInsurances(string $logPath): int
    {
        try {
            Insurance::resetInsurances();
            Writer::write('insurance reset completed', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        return 0;
    }

    private function subDedupeInsuranceRelations(string $logPath): int
    {
        try {
            foreach (Insurance::dedupeDuplicateInsuranceRelationRows() as $line) {
                Writer::write($line, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                $this->output->writeln($line, null);
            }
        } catch (Exception $e) {
            $this->logImportException($e, $logPath, 'dedupe_insurance_relations failed');
            return 1;
        }
        return 0;
    }

    private function subTouristic(array $ids, string $logPath): int
    {
        if (empty($ids)) {
            $this->output->writeln('Missing mediaobject id(s)', null);
            return 1;
        }
        $importer = new Import('touristic');
        Writer::write('Importing touristic data for mediaobject ID(s): ' . implode(',', $ids), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer->importTouristicDataFromArray($ids);
            Writer::write('Touristic import done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $importer->postImport($ids);
            $this->reportErrors($importer, $logPath);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        }
        $importedIds = $importer->getImportedIds();
        $this->invokeAfterImportCallback($importedIds, $importer);
        if (!$this->hasOption('no-validate')) {
            foreach ($importedIds as $id) {
                $this->output->writeln('===== Validation =====', null);
                $mediaObject = new MediaObject($id);
                $r = $mediaObject->validate();
                $this->output->writeln(implode("\n", $r), null);
            }
        }
        return 0;
    }

    private function subFullimportTouristic(string $logPath): int
    {
        $importer = new Import('fullimport_touristic');
        Writer::write('Importing touristic data for all media objects', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        try {
            $importer->getIDsToImport();
            $ids = $importer->getMediaObjectsFromFolder();
            Writer::write('Found ' . count($ids) . ' media objects to update', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            if (empty($ids)) {
                Writer::write('No media objects found', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            } else {
                foreach ($ids as $id) {
                    $importer->importTouristicDataOnly($id);
                    \Pressmind\ORM\Object\Import\Queue::remove($id);
                }
            }
            $this->reportErrors($importer, $logPath);
            Writer::write('Touristic fullimport done.', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        } catch (Exception $e) {
            $this->logImportException($e, $logPath);
            return 1;
        } finally {
            $importer->postImport();
            $importedIds = $importer->getImportedIds();
            $this->invokeAfterImportCallback($importedIds, $importer);
            $this->output->writeln(implode(', ', $importedIds), null);
        }
        return 0;
    }

    private function printHelp(): void
    {
        $helptext = "usage: import.php [fullimport | sync | fullimport_touristic | mediaobject | touristic | itinerary | objecttypes | remove_orphans | destroy | depublish | update_tags | offer | calendar | remove_orphans | update_tags | postimport | categories | unlock | create_translations | filestorage] [<single id or commaseparated list of ids>] [debug]\n";
        $helptext .= "Example usages:\n";
        $helptext .= "php import.php fullimport\n";
        $helptext .= "php import.php sync                    <hash-based delta import; only re-imports changed objects, recommended for cron>\n";
        $helptext .= "php import.php fullimport -c=pm-config-example.php <loads the defined config>\n";
        $helptext .= "php import.php fullimport_touristic     <imports only touristic data for all media objects without reimporting the media objects themselves>\n";
        $helptext .= "php import.php mediaobject 12345,12346  <single/multiple ids allowed  / imports one or more media objects>\n";
        $helptext .= "php import.php touristic 12345,12346    <single/multiple ids allowed / imports only touristic data without reimporting the media object>\n";
        $helptext .= "php import.php objecttypes 12345,12346  <single/multiple ids allowed / imports the object type model (NOT the media objects in this type)>\n";
        $helptext .= "php import.php itinerary 12345,12346    <single/multiple ids allowed / imports itineraries for the given media object types>\n";
        $helptext .= "php import.php destroy 12345,12346      <single/multiple ids allowed / removes the given media objects from the database>\n";
        $helptext .= "php import.php depublish 12345,12346    <single/multiple ids allowed / sets the given media objects to the visibility=10/nobody state>\n";
        $helptext .= "php import.php offer 12345,12346        <single/multiple ids allowed / recalculates offers/cheapestPrices\n";
        $helptext .= "php import.php calendar 12345,12346     <single/multiple ids allowed / recalculates offers/cheapestPrices\n";
        $helptext .= "php import.php remove_orphans           <removes all orphans from the database that are not delivered by the pressmind api>\n";
        $helptext .= "php import.php update_tags 12345        <single media object type id required>\n";
        $helptext .= "php import.php postimport 12345         <single/multiple ids allowed but optional / runs the post import routine (image_processors and triggers custom_post_import_hooks scripts if configured)> \n";
        $helptext .= "php import.php categories 123,124       <single/multiple ids allowed / imports all category trees> \n";
        $helptext .= "php import.php unlock                   <removes the lock file - be carefull!> \n";
        $helptext .= "php import.php create_translations      <creates gettext *.mo translation files> \n";
        $helptext .= "php import.php reset_insurances         <truncates all insurance related tables> \n";
        $helptext .= "php import.php dedupe_insurance_relations <removes duplicate rows in insurance_to_insurance / insurance_to_alternate before integrity-check> \n";
        $helptext .= "php import.php powerfilter              <import powerfilters> \n";
        $helptext .= "php import.php filestorage              <imports FileStorage metadata into attachments; optional: --force --folder=<id> --no-download> \n";
        $helptext .= "Options: --validate (run validation after fullimport/sync; off by default). --no-validate (skip validation after mediaobject/touristic). --force (ignore hash for single mediaobject import). \n";
        $this->output->write($helptext, null);
    }
}

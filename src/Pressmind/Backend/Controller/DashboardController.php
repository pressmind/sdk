<?php

namespace Pressmind\Backend\Controller;

use Pressmind\ORM\Object\Import\Queue;
use Pressmind\ORM\Object\Log;
use Pressmind\ORM\Object\ProcessList;
use Pressmind\Registry;

/**
 * Dashboard: system status, image formats, last errors, stats.
 * Replaces show_image_formats.php pattern.
 */
class DashboardController extends AbstractController
{
    public function indexAction(): void
    {
        $systemInfo = $this->getSystemInfo();
        $lastErrors = $this->getLastErrors(10);
        $importLock = $this->getImportLock();
        $queueCount = $this->getQueueCount();
        $processList = $this->getProcessList();
        $mediaObjectStats = $this->getMediaObjectStats();
        $configEnv = $this->getConfigEnvLabel();
        $imageCacheMissing = $this->getImageCacheMissingCount();
        $this->render('dashboard/index.php', [
            'title' => 'Dashboard',
            'systemInfo' => $systemInfo,
            'lastErrors' => $lastErrors,
            'importLock' => $importLock,
            'queueCount' => $queueCount,
            'processList' => $processList,
            'mediaObjectStats' => $mediaObjectStats,
            'configEnv' => $configEnv,
            'imageCacheMissing' => $imageCacheMissing,
        ]);
    }

    /**
     * Legacy: System page was removed; redirect to dashboard.
     */
    public function systemAction(): void
    {
        header('Location: ' . (isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '') . '?page=dashboard');
        exit;
    }

    public function imageFormatsAction(): void
    {
        $imageFormats = $this->getImageFormats();
        $baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        $baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
        $this->render('dashboard/image-formats.php', [
            'title' => 'Image Formats',
            'imageFormats' => $imageFormats,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @return array{php_version: string, sdk_version: string, db_name: string, db_host: string, redis: string, mongodb: string}
     */
    private function getSystemInfo(): array
    {
        $sdkInfo = $this->getSdkVersionInfo();
        $info = [
            'php_version' => PHP_VERSION,
            'sdk_version' => $sdkInfo['version'],
            'sdk_date' => $sdkInfo['date'],
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname() ?: 'localhost'),
            'db_name' => '-',
            'db_host' => '-',
            'redis' => 'unknown',
            'mongodb' => 'unknown',
        ];
        try {
            $config = Registry::getInstance()->get('config');
            if (!empty($config['database']['dbname'])) {
                $info['db_name'] = $config['database']['dbname'];
            }
            if (!empty($config['database']['host'])) {
                $info['db_host'] = $config['database']['host'];
            }
            if (!empty($config['cache']['enabled']) && isset($config['cache']['adapter']['name']) && $config['cache']['adapter']['name'] === 'Redis') {
                $info['redis'] = 'configured';
            } else {
                $info['redis'] = 'disabled';
            }
            if (!empty($config['data']['search_mongodb']['enabled'])) {
                $info['mongodb'] = 'enabled';
            } else {
                $info['mongodb'] = 'disabled';
            }
        } catch (\Throwable $e) {
            // keep defaults
        }
        return $info;
    }

    /**
     * Return loaded config environment label (DEV or PROD) for display.
     */
    private function getConfigEnvLabel(): string
    {
        $env = null;
        if (defined('ENV')) {
            $env = ENV;
        } elseif (getenv('APP_ENV') !== false) {
            $env = getenv('APP_ENV');
        }
        if ($env === null || $env === '') {
            return '-';
        }
        return strtolower((string) $env) === 'production' ? 'PROD' : 'DEV';
    }

    /**
     * @return array<int, array{id: int, date: string, type: string, text: string, category: string}>
     */
    private function getLastErrors(int $limit): array
    {
        try {
            $log = new Log();
            $logs = $log->loadAll("type IN ('ERROR', 'FATAL')", ['date' => 'DESC'], [0, $limit]);
            $out = [];
            foreach ($logs as $l) {
                $out[] = [
                    'id' => $l->id,
                    'date' => $l->date instanceof \DateTimeInterface ? $l->date->format('Y-m-d H:i:s') : (string) $l->date,
                    'type' => $l->type,
                    'text' => $l->text,
                    'category' => $l->category ?? '',
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{name: string, pid: int, created_at: string}|null
     */
    private function getImportLock(): ?array
    {
        try {
            $lock = ProcessList::getLock('import');
            if ($lock === null) {
                return null;
            }
            return [
                'name' => $lock->name,
                'pid' => $lock->pid,
                'created_at' => $lock->created_at instanceof \DateTimeInterface ? $lock->created_at->format('Y-m-d H:i:s') : (string) $lock->created_at,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getQueueCount(): int
    {
        try {
            $q = new Queue();
            $all = $q->loadAll(null, null, [0, 10000]);
            return is_array($all) ? count($all) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array<int, array{id: int, name: string, pid: int, timeout: int, created_at: string}>
     */
    private function getProcessList(): array
    {
        try {
            $list = new ProcessList();
            $all = $list->loadAll(null, ['created_at' => 'DESC'], [0, 100]);
            $out = [];
            foreach (is_array($all) ? $all : [] as $p) {
                $out[] = [
                    'id' => (int) $p->id,
                    'name' => (string) ($p->name ?? ''),
                    'pid' => (int) ($p->pid ?? 0),
                    'timeout' => (int) ($p->timeout ?? 0),
                    'created_at' => $p->created_at instanceof \DateTimeInterface ? $p->created_at->format('Y-m-d H:i:s') : (string) ($p->created_at ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array{id_object_type: int, visibility: int, count: int}>
     */
    private function getMediaObjectStats(): array
    {
        try {
            $db = Registry::getInstance()->get('db');
            $prefix = $db->getTablePrefix();
            $sql = "SELECT id_object_type, visibility, COUNT(*) AS cnt FROM " . $prefix . "pmt2core_media_objects GROUP BY id_object_type, visibility ORDER BY id_object_type, visibility";
            $rows = $db->fetchAll($sql);
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id_object_type' => (int) $r->id_object_type,
                    'visibility' => (int) $r->visibility,
                    'count' => (int) $r->cnt,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Detect the installed SDK version and install/update date via Composer's InstalledVersions.
     * @return array{version: string, date: string}
     */
    private function getSdkVersionInfo(): array
    {
        $info = ['version' => 'dev (not installed via Composer)', 'date' => '-'];
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                $version = \Composer\InstalledVersions::getPrettyVersion('pressmind/sdk');
                if ($version !== null && $version !== '') {
                    $ref = \Composer\InstalledVersions::getReference('pressmind/sdk');
                    if ($ref !== null && strlen($ref) >= 7) {
                        $info['version'] = $version . ' (' . substr($ref, 0, 7) . ')';
                    } else {
                        $info['version'] = $version;
                    }
                    // Determine package date from install path
                    $installPath = \Composer\InstalledVersions::getInstallPath('pressmind/sdk');
                    if ($installPath !== null && is_dir($installPath)) {
                        $composerFile = $installPath . '/composer.json';
                        if (is_file($composerFile)) {
                            $mtime = filemtime($composerFile);
                            if ($mtime !== false) {
                                $info['date'] = date('Y-m-d H:i:s', $mtime);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Composer runtime not available
        }
        return $info;
    }

    /**
     * Count image records with download_successful = 0 (not yet processed) across pictures, sections, documents.
     */
    private function getImageCacheMissingCount(): int
    {
        try {
            $db = Registry::getInstance()->get('db');
            $prefix = $db->getTablePrefix();
            $tables = [
                $prefix . 'pmt2core_media_object_images',
                $prefix . 'pmt2core_media_object_image_sections',
                $prefix . 'pmt2core_itinerary_step_document_media_objects',
            ];
            $total = 0;
            foreach ($tables as $table) {
                $row = $db->fetchRow("SELECT COUNT(*) AS c FROM " . $table . " WHERE download_successful = 0");
                $total += $row && isset($row->c) ? (int) $row->c : 0;
            }
            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed> Image derivatives from config
     */
    private function getImageFormats(): array
    {
        try {
            $config = Registry::getInstance()->get('config');
            $derivatives = $config['image_handling']['processor']['derivatives'] ?? [];
            return is_array($derivatives) ? $derivatives : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

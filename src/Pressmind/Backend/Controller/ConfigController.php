<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Registry;

/**
 * Config editor: section-based view, raw JSON, optional save via config_adapter.
 */
class ConfigController extends AbstractController
{
    private const SECTIONS = [
        'server' => 'Server',
        'database' => 'Database',
        'backend' => 'Backend',
        'rest' => 'REST API',
        'ib3' => 'IBE',
        'logging' => 'Logging',
        'data' => 'Data (Touristic, Import, Search)',
        'price_format' => 'Price Format',
        'cache' => 'Cache',
        'image_handling' => 'Image Handling',
        'file_handling' => 'File Handling',
    ];

    public function indexAction(): void
    {
        $config = $this->getConfig();
        $sections = [];
        foreach (self::SECTIONS as $key => $label) {
            $sections[$key] = ['label' => $label, 'has_data' => isset($config[$key])];
        }
        $this->render('config/index.php', [
            'title' => 'Config',
            'sections' => $sections,
        ]);
    }

    public function sectionAction(): void
    {
        $section = $this->get('section');
        if ($section === null || $section === '' || !isset(self::SECTIONS[$section])) {
            $this->redirect($this->baseUrl() . 'page=config');
            return;
        }
        $config = $this->getConfig();
        $data = $config[$section] ?? [];
        $this->render('config/section.php', [
            'title' => 'Config: ' . (self::SECTIONS[$section] ?? $section),
            'section' => $section,
            'sectionLabel' => self::SECTIONS[$section] ?? $section,
            'data' => $data,
        ]);
    }

    public function rawAction(): void
    {
        $config = $this->getConfig();
        $this->render('config/raw.php', [
            'title' => 'Config Raw',
            'config' => $config,
            'configJson' => json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Diff between two config environments (e.g. development vs production).
     */
    public function diffAction(): void
    {
        $adapter = $this->getConfigAdapter();
        $envs = ['development' => 'Development', 'production' => 'Production', 'testing' => 'Testing'];
        $leftKey = $this->get('left');
        $rightKey = $this->get('right');
        if (!is_string($leftKey) || !isset($envs[$leftKey])) {
            $leftKey = 'development';
        }
        if (!is_string($rightKey) || !isset($envs[$rightKey])) {
            $rightKey = 'production';
        }

        $allConfigs = [];
        $error = null;
        if ($adapter !== null && method_exists($adapter, 'readAllEnvironments')) {
            try {
                $allConfigs = $adapter->readAllEnvironments();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = $adapter === null ? 'Config adapter not registered in Registry.' : 'Adapter does not support readAllEnvironments().';
        }

        $leftConfig = $allConfigs[$leftKey] ?? [];
        $rightConfig = $allConfigs[$rightKey] ?? [];
        $diff = $this->computeConfigDiff($leftConfig, $rightConfig);

        $this->render('config/diff.php', [
            'title' => 'Config Diff',
            'envs' => $envs,
            'leftKey' => $leftKey,
            'rightKey' => $rightKey,
            'leftConfig' => $leftConfig,
            'rightConfig' => $rightConfig,
            'diff' => $diff,
            'error' => $error,
            'baseUrl' => $this->baseUrl(),
        ]);
    }

    /**
     * Recursive diff of two config arrays. Returns a flat list: path => ['status' => only_left|only_right|changed, 'left' => val, 'right' => val].
     *
     * @return array<string, array{status: string, left: mixed, right: mixed}>
     */
    private function computeConfigDiff(array $left, array $right, string $prefix = ''): array
    {
        $result = [];
        $allKeys = array_unique(array_merge(array_keys($left), array_keys($right)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $path = $prefix !== '' ? $prefix . '.' . $key : $key;
            $inLeft = array_key_exists($key, $left);
            $inRight = array_key_exists($key, $right);

            if (!$inLeft) {
                $result[$path] = ['status' => 'only_right', 'left' => null, 'right' => $right[$key]];
                continue;
            }
            if (!$inRight) {
                $result[$path] = ['status' => 'only_left', 'left' => $left[$key], 'right' => null];
                continue;
            }

            $leftVal = $left[$key];
            $rightVal = $right[$key];

            if (is_array($leftVal) && is_array($rightVal) && $this->isAssocArray($leftVal) && $this->isAssocArray($rightVal)) {
                $result = array_merge($result, $this->computeConfigDiff($leftVal, $rightVal, $path));
                continue;
            }

            if ($leftVal === $rightVal) {
                continue;
            }
            $result[$path] = ['status' => 'changed', 'left' => $leftVal, 'right' => $rightVal];
        }

        return $result;
    }

    private function isAssocArray(array $a): bool
    {
        if ($a === []) {
            return true;
        }
        return array_keys($a) !== range(0, count($a) - 1);
    }

    private function getConfigAdapter(): ?object
    {
        try {
            return Registry::getInstance()->get('config_adapter');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }
        $nonce = $this->getAuth()->createNonce('config_save');
        if ($nonce === null || !$this->getAuth()->verifyNonce($_POST['_pm_nonce'] ?? '', 'config_save')) {
            $this->json(['success' => false, 'message' => 'Invalid nonce'], 403);
            return;
        }
        try {
            $adapter = Registry::getInstance()->get('config_adapter');
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Config adapter not registered. Add config_adapter to Registry for writing.'], 501);
            return;
        }
        if (!method_exists($adapter, 'write')) {
            $this->json(['success' => false, 'message' => 'Config adapter does not support write().'], 501);
            return;
        }
        $body = file_get_contents('php://input');
        if ($body !== '' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $data = json_decode($body, true);
            if (!is_array($data)) {
                $this->json(['success' => false, 'message' => 'Invalid JSON'], 400);
                return;
            }
        } else {
            $this->json(['success' => false, 'message' => 'Send JSON body for save'], 400);
            return;
        }
        try {
            $adapter->write($data);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** @return array<string, mixed> */
    private function getConfig(): array
    {
        try {
            $config = Registry::getInstance()->get('config');
            return is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function baseUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base . '?' : '?');
    }
}

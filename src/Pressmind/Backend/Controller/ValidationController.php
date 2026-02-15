<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Registry;
use Pressmind\System\TouristicOrphans;

/**
 * Validation: Touristic Orphans.
 */
class ValidationController extends AbstractController
{
    public function indexAction(): void
    {
        $this->render('validation/index.php', ['title' => 'Validation']);
    }

    public function orphansAction(): void
    {
        $objectTypeIds = $this->getObjectTypeIds();
        $orphanizer = new TouristicOrphans();
        $allOrphans = $orphanizer->findOrphans($objectTypeIds, 30);
        $searchQ = $this->get('q');
        if ($searchQ !== null && $searchQ !== '') {
            $searchQ = trim($searchQ);
            $allOrphans = array_filter($allOrphans, function ($o) use ($searchQ) {
                $id = (string) ($o->id ?? '');
                $code = (string) ($o->code ?? '');
                $name = (string) ($o->name ?? '');
                return stripos($id, $searchQ) !== false || stripos($code, $searchQ) !== false || stripos($name, $searchQ) !== false;
            });
        }
        $totalMOs = $this->getVisibleMediaObjectCount($objectTypeIds);
        $orphanCount = count($allOrphans);
        $okCount = $totalMOs - $orphanCount;
        $perPage = 50;
        $pageNum = max(1, (int) $this->get('page_num', 1));
        $totalPages = max(1, (int) ceil($orphanCount / $perPage));
        $offset = ($pageNum - 1) * $perPage;
        $orphans = array_slice($allOrphans, $offset, $perPage);
        $paginationBaseUrl = $this->baseUrl() . 'page=validation&action=orphans&';
        if ($searchQ !== null && $searchQ !== '') {
            $paginationBaseUrl .= 'q=' . urlencode($searchQ) . '&';
        }
        $this->render('validation/orphans.php', [
            'title' => 'Touristic Orphans',
            'orphans' => $orphans,
            'totalMOs' => $totalMOs,
            'orphanCount' => $orphanCount,
            'okCount' => max(0, $okCount),
            'baseUrl' => $this->baseUrl(),
            'searchQ' => $searchQ,
            'pageNum' => $pageNum,
            'totalPages' => $totalPages,
            'total' => $orphanCount,
            'paginationBaseUrl' => $paginationBaseUrl,
        ]);
    }

    public function orphanDetailAction(): void
    {
        $id = (int) $this->get('id');
        if ($id <= 0) {
            $this->redirect($this->baseUrl() . 'page=validation&action=orphans');
            return;
        }
        $orphanizer = new TouristicOrphans();
        $detail = $orphanizer->getOrphanDetails($id);
        $baseUrl = $this->baseUrl();
        $this->render('validation/orphan-detail.php', [
            'title' => 'Orphan Detail ' . $id,
            'id' => $id,
            'detail' => $detail,
            'baseUrl' => $baseUrl,
            'streamBase' => $baseUrl . 'page=import&action=stream',
        ]);
    }

    /** @return array<int, int> */
    private function getObjectTypeIds(): array
    {
        try {
            $config = Registry::getInstance()->get('config');
            $ids = $config['data']['primary_media_type_ids'] ?? null;
            if (is_array($ids) && !empty($ids)) {
                return array_map('intval', $ids);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return [123, 124, 125, 126];
    }

    private function getVisibleMediaObjectCount(array $objectTypeIds): int
    {
        if (empty($objectTypeIds)) {
            return 0;
        }
        try {
            $db = Registry::getInstance()->get('db');
            $prefix = $db->getTablePrefix();
            $placeholders = implode(',', array_fill(0, count($objectTypeIds), '?'));
            $params = array_merge($objectTypeIds, [30]);
            $row = $db->fetchRow("SELECT COUNT(*) AS c FROM " . $prefix . "pmt2core_media_objects WHERE id_object_type IN ($placeholders) AND visibility = ?", $params);
            return $row && isset($row->c) ? (int) $row->c : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function baseUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base . '?' : '?');
    }
}

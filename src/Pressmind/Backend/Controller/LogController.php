<?php

namespace Pressmind\Backend\Controller;

use Pressmind\ORM\Object\Log;
use Pressmind\Registry;

/**
 * Log browser: pmt2core_logs with filters and pagination.
 */
class LogController extends AbstractController
{
    private const PER_PAGE = 50;
    private const ALLOWED_TYPES = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'FATAL'];

    public function indexAction(): void
    {
        $type = $this->get('type');
        $category = $this->get('category');
        $searchQ = $this->get('q');
        $pageNum = max(1, (int) $this->get('page_num', 1));

        $where = [];
        if ($type !== null && $type !== '' && in_array($type, self::ALLOWED_TYPES, true)) {
            $where['type'] = $type;
        }
        if ($category !== null && $category !== '') {
            $where['category'] = $category;
        }
        if ($searchQ !== null && $searchQ !== '') {
            $where['_text_search'] = '%' . $searchQ . '%';
        }

        $log = new Log();
        $total = $this->getLogCount($where);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $offset = ($pageNum - 1) * self::PER_PAGE;

        $rows = $this->loadLogRows($log, $where, $offset, self::PER_PAGE);

        $rowsPlain = [];
        foreach ($rows as $r) {
            $rowsPlain[] = [
                'id' => $r->id,
                'date' => $r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d H:i:s') : (string) $r->date,
                'type' => $r->type,
                'category' => $r->category ?? '',
                'text' => $r->text ?? '',
                'trace' => $r->trace ?? '',
            ];
        }

        $baseUrl = $this->buildLogBaseUrl();
        $this->render('log/index.php', [
            'title' => 'Logs',
            'rows' => $rowsPlain,
            'type' => $type,
            'category' => $category,
            'searchQ' => $searchQ,
            'pageNum' => $pageNum,
            'totalPages' => $totalPages,
            'total' => $total,
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * @param array<string, mixed> $where
     * @return Log[]
     */
    private function loadLogRows(Log $log, array $where, int $offset, int $limit): array
    {
        $textSearch = $where['_text_search'] ?? null;
        unset($where['_text_search']);
        if ($textSearch !== null) {
            $db = Registry::getInstance()->get('db');
            $table = $log->getDbTableName();
            $conditions = ['text LIKE ?'];
            $params = [$textSearch];
            foreach ($where as $k => $v) {
                $conditions[] = $k . ' = ?';
                $params[] = $v;
            }
            $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY date DESC LIMIT ' . (int) $offset . ', ' . (int) $limit;
            $rows = $db->fetchAll($sql, $params);
            $out = [];
            foreach ($rows as $row) {
                $obj = new Log();
                $obj->id = $row->id ?? null;
                $obj->date = isset($row->date) ? (\is_object($row->date) ? $row->date : new \DateTime($row->date)) : null;
                $obj->type = $row->type ?? '';
                $obj->category = $row->category ?? '';
                $obj->text = $row->text ?? '';
                $obj->trace = $row->trace ?? '';
                $out[] = $obj;
            }
            return $out;
        }
        return $log->loadAll(
            $where === [] ? null : $where,
            ['date' => 'DESC'],
            [$offset, $limit]
        );
    }

    private function getLogCount(array $where): int
    {
        try {
            $db = Registry::getInstance()->get('db');
            $table = (new Log())->getDbTableName();
            $textSearch = $where['_text_search'] ?? null;
            unset($where['_text_search']);
            $conditions = [];
            $params = [];
            if ($textSearch !== null) {
                $conditions[] = 'text LIKE ?';
                $params[] = $textSearch;
            }
            foreach ($where as $k => $v) {
                $conditions[] = $k . ' = ?';
                $params[] = $v;
            }
            $sql = 'SELECT COUNT(*) AS cnt FROM ' . $table;
            if ($conditions !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $row = $db->fetchRow($sql, $params);
            return $row && isset($row->cnt) ? (int) $row->cnt : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Truncate all log entries (POST only).
     */
    public function truncateAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->logPageUrl());
            return;
        }
        try {
            $db = Registry::getInstance()->get('db');
            $table = (new Log())->getDbTableName();
            $db->execute('TRUNCATE TABLE ' . $table);
        } catch (\Throwable $e) {
            // silently ignore
        }
        $this->redirect($this->logPageUrl());
    }

    /**
     * Build the base URL for the logs page (without filters).
     */
    private function logPageUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base : '') . '?page=logs';
    }

    private function buildLogBaseUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        $base = ($base !== '' ? $base . '?' : '?');
        $params = ['page' => 'logs'];
        $type = $this->get('type');
        $category = $this->get('category');
        $searchQ = $this->get('q');
        if ($type !== null && $type !== '') {
            $params['type'] = $type;
        }
        if ($category !== null && $category !== '') {
            $params['category'] = $category;
        }
        if ($searchQ !== null && $searchQ !== '') {
            $params['q'] = $searchQ;
        }
        return $base . http_build_query($params) . '&';
    }
}

<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Registry;

/**
 * Data browser: tables, Media Object detail.
 */
class DataController extends AbstractController
{
    private const PER_PAGE = 50;

    public function indexAction(): void
    {
        $tableSearch = $this->get('q');
        $tableSearch = is_string($tableSearch) ? trim($tableSearch) : '';
        $rawTables = $this->getTableCounts();
        $objectTypeInfo = $this->getObjectTypeInfoFromConfig();
        $rows = [];
        foreach ($rawTables as $name => $count) {
            if ($tableSearch !== '' && stripos($name, $tableSearch) === false) {
                continue;
            }
            $objectTypeName = null;
            $isPrimary = false;
            if (preg_match('/^objectdata_(\d+)$/', $name, $m)) {
                $id = (int) $m[1];
                $objectTypeName = $objectTypeInfo['names'][$id] ?? null;
                $isPrimary = isset($objectTypeInfo['primary_ids'][$id]);
            }
            $rows[] = [
                'name' => $name,
                'count' => $count,
                'objectTypeName' => $objectTypeName,
                'isPrimary' => $isPrimary,
            ];
        }
        $this->render('data/index.php', [
            'title' => 'Data',
            'tableRows' => $rows,
            'tableSearch' => $tableSearch,
        ]);
    }

    /**
     * @return array{names: array<int, string>, primary_ids: array<int, true>}
     */
    private function getObjectTypeInfoFromConfig(): array
    {
        $names = [];
        $primaryIds = [];
        try {
            $config = Registry::getInstance()->get('config');
            $mediaTypes = $config['data']['media_types'] ?? [];
            if (is_array($mediaTypes)) {
                foreach ($mediaTypes as $id => $label) {
                    $id = (int) $id;
                    $names[$id] = is_string($label) ? $label : (string) $label;
                }
            }
            $primary = $config['data']['primary_media_type_ids'] ?? [];
            if (is_array($primary)) {
                foreach ($primary as $id) {
                    $primaryIds[(int) $id] = true;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return ['names' => $names, 'primary_ids' => $primaryIds];
    }

    public function tableAction(): void
    {
        $table = $this->get('table');
        if ($table === null || $table === '') {
            $this->redirect($this->requestUrl('page=data'));
            return;
        }
        $pageNum = max(1, (int) $this->get('page_num', 1));
        $perPage = (int) $this->get('per_page', self::PER_PAGE);
        if (!in_array($perPage, [25, 50, 100, 250], true)) {
            $perPage = self::PER_PAGE;
        }
        $searchQ = $this->get('q');
        $filterCols = $this->get('filter');
        if (!is_array($filterCols)) {
            $filterCols = [];
        }
        $sortCol = $this->get('sort');
        $sortDir = strtoupper((string) $this->get('sort_dir', 'DESC'));
        if ($sortDir !== 'ASC' && $sortDir !== 'DESC') {
            $sortDir = 'DESC';
        }
        try {
            $db = Registry::getInstance()->get('db');
            $prefix = $db->getTablePrefix();
            $fullName = $prefix . $table;
            $headers = $this->getTableColumns($db, $fullName);
            $filterCols = array_intersect_key($filterCols, array_flip($headers));
            $orderColumn = $this->resolveOrderColumn($headers, $sortCol);
            $where = [];
            $params = [];
            $escapedTable = '`' . str_replace('`', '``', $fullName) . '`';
            if ($searchQ !== null && $searchQ !== '') {
                $orParts = [];
                foreach ($headers as $col) {
                    $orParts[] = '`' . str_replace('`', '``', $col) . '` LIKE ?';
                    $params[] = '%' . $searchQ . '%';
                }
                $where[] = '(' . implode(' OR ', $orParts) . ')';
            }
            foreach ($headers as $col) {
                $fVal = $filterCols[$col] ?? null;
                if ($fVal !== null && $fVal !== '') {
                    $where[] = '`' . str_replace('`', '``', $col) . '` LIKE ?';
                    $params[] = '%' . $fVal . '%';
                }
            }
            $sqlBase = 'SELECT * FROM ' . $escapedTable;
            if ($where !== []) {
                $sqlBase .= ' WHERE ' . implode(' AND ', $where);
            }
            $total = $this->getTableCountFiltered($db, $fullName, $where, $params);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $offset = ($pageNum - 1) * $perPage;
            $orderEsc = '`' . str_replace('`', '``', $orderColumn) . '`';
            $sql = $sqlBase . ' ORDER BY ' . $orderEsc . ' ' . $sortDir . ' LIMIT ' . (int) $offset . ', ' . (int) $perPage;
            $rows = $db->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
            $headers = [];
            $total = 0;
            $totalPages = 1;
            $orderColumn = 'id';
        }
        $maximize = $this->get('maximize') === '1' || $this->get('maximize') === 'true';
        $transpose = $this->get('transpose') === '1' || $this->get('transpose') === 'true';
        $baseUrl = $this->buildTableBaseUrl($table, $searchQ, $filterCols, $sortCol, $sortDir, $perPage);
        $this->render('data/table.php', [
            'title' => 'Table: ' . $table,
            'table' => $table,
            'headers' => $headers,
            'rows' => $rows,
            'pageNum' => $pageNum,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'baseUrl' => $baseUrl,
            'searchQ' => $searchQ,
            'filterCols' => $filterCols,
            'sortCol' => $sortCol,
            'sortDir' => $sortDir,
            'maximize' => $maximize,
            'transpose' => $transpose,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getTableColumns($db, string $fullTableName): array
    {
        $esc = str_replace('`', '``', $fullTableName);
        $desc = $db->fetchAll('DESCRIBE `' . $esc . '`');
        $cols = [];
        foreach ($desc as $row) {
            $field = isset($row->Field) ? $row->Field : (isset($row->field) ? $row->field : null);
            if ($field !== null && $field !== '') {
                $cols[] = $field;
            }
        }
        return $cols;
    }

    private function resolveOrderColumn(array $headers, ?string $sortCol): string
    {
        if ($sortCol !== null && $sortCol !== '' && in_array($sortCol, $headers, true)) {
            return $sortCol;
        }
        return $headers !== [] ? $headers[0] : 'id';
    }

    private function getTableCountFiltered(\Pressmind\DB\Adapter\Pdo $db, string $fullName, array $where, array $params): int
    {
        $esc = '`' . str_replace('`', '``', $fullName) . '`';
        $sql = 'SELECT COUNT(*) AS c FROM ' . $esc;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $row = $db->fetchRow($sql, $params);
        return $row && isset($row->c) ? (int) $row->c : 0;
    }

    /**
     * @param array<string, string> $filterCols
     */
    private function buildTableBaseUrl(string $table, ?string $searchQ, array $filterCols, ?string $sortCol, string $sortDir, int $perPage): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        $base = ($base !== '' ? $base . '?' : '?');
        $p = ['page' => 'data', 'action' => 'table', 'table' => $table];
        if ($searchQ !== null && $searchQ !== '') {
            $p['q'] = $searchQ;
        }
        foreach ($filterCols as $k => $v) {
            if ($v !== null && $v !== '') {
                $p['filter'][$k] = $v;
            }
        }
        if ($sortCol !== null && $sortCol !== '') {
            $p['sort'] = $sortCol;
            $p['sort_dir'] = $sortDir;
        }
        if ($perPage !== self::PER_PAGE) {
            $p['per_page'] = $perPage;
        }
        return $base . http_build_query($p, '', '&', PHP_QUERY_RFC3986) . '&';
    }

    /**
     * Legacy: Media Object detail page was removed; redirect to Data index.
     */
    public function mediaObjectAction(): void
    {
        $this->redirect($this->requestUrl('page=data'));
    }

    /**
     * @return array<string, int> table name (without prefix) => row count
     */
    private function getTableCounts(): array
    {
        $out = [];
        try {
            $db = Registry::getInstance()->get('db');
            $prefix = $db->getTablePrefix();
            $prefix = $prefix !== null ? (string) $prefix : '';
            $rows = $db->fetchAll('SHOW TABLES');
            foreach ($rows as $row) {
                $arr = (array) $row;
                $fullName = reset($arr);
                if ($fullName === null || $fullName === '') {
                    continue;
                }
                $fullName = (string) $fullName;
                $shortName = ($prefix !== '' && strpos($fullName, $prefix) === 0)
                    ? substr($fullName, strlen($prefix))
                    : $fullName;
                $out[$shortName] = $this->getTableCount($fullName);
            }
            ksort($out, SORT_STRING);
        } catch (\Throwable $e) {
            // leave empty
        }
        return $out;
    }

    private function getTableCount(string $fullTableName): int
    {
        try {
            $db = Registry::getInstance()->get('db');
            $row = $db->fetchRow('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $fullTableName) . '`');
            return $row && isset($row->c) ? (int) $row->c : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function requestUrl(string $query): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base . '?' : '?') . $query;
    }

}

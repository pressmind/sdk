<?php
$table = $table ?? '';
$headers = $headers ?? [];
$rows = $rows ?? [];
$pageNum = (int) ($pageNum ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total = (int) ($total ?? 0);
$perPage = (int) ($perPage ?? 50);
$baseUrl = $baseUrl ?? '?';
$searchQ = $searchQ ?? '';
$filterCols = $filterCols ?? [];
$sortCol = $sortCol ?? '';
$sortDir = $sortDir ?? 'DESC';
$maximize = !empty($maximize);
$transpose = !empty($transpose);
$tableBase = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$tableBase = ($tableBase !== '' ? $tableBase . '?' : '?');
$tableQuery = ['page' => 'data', 'action' => 'table', 'table' => $table];
if ($searchQ !== '') {
    $tableQuery['q'] = $searchQ;
}
foreach ($filterCols as $k => $v) {
    if ($v !== '') {
        $tableQuery['filter'][$k] = $v;
    }
}
if ($sortCol !== '') {
    $tableQuery['sort'] = $sortCol;
    $tableQuery['sort_dir'] = $sortDir;
}
if ($perPage !== 50) {
    $tableQuery['per_page'] = $perPage;
}
if ($maximize) {
    $tableQuery['maximize'] = '1';
}
if ($transpose) {
    $tableQuery['transpose'] = '1';
}
$hasActiveFilters = ($searchQ !== '' || array_filter($filterCols, static function ($v) { return $v !== ''; }) !== []);
$clearFiltersUrl = $tableBase . http_build_query(['page' => 'data', 'action' => 'table', 'table' => $table], '', '&', PHP_QUERY_RFC3986);
function tableSortUrl(string $base, array $query, string $col, string $currentDir): string {
    $q = $query;
    $q['sort'] = $col;
    $q['sort_dir'] = ($q['sort'] ?? '') === $col && $currentDir === 'DESC' ? 'ASC' : 'DESC';
    return $base . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}
?>
<h1 class="h3 mb-4">Table: <?php echo htmlspecialchars($table); ?></h1>

<form method="get" action="" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="data">
    <input type="hidden" name="action" value="table">
    <input type="hidden" name="table" value="<?php echo htmlspecialchars($table); ?>">
    <?php foreach ($filterCols as $k => $v) {
        if ($v !== '') {
            echo '<input type="hidden" name="filter[' . htmlspecialchars($k) . ']" value="' . htmlspecialchars($v) . '">';
        }
    } ?>
    <?php if ($sortCol !== '') { ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortCol); ?>">
        <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDir); ?>">
    <?php } ?>
    <?php if ($maximize) { ?>
        <input type="hidden" name="maximize" value="1">
    <?php } ?>
    <?php if ($transpose) { ?>
        <input type="hidden" name="transpose" value="1">
    <?php } ?>
    <div class="col-auto">
        <label for="data-table-q" class="form-label small mb-0">Search (all columns)</label>
        <input type="text" class="form-control form-control-sm" id="data-table-q" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Search…">
    </div>
    <div class="col-auto">
        <label for="data-table-pp" class="form-label small mb-0">Per page</label>
        <select name="per_page" id="data-table-pp" class="form-select form-select-sm">
            <?php foreach ([25, 50, 100, 250] as $n) {
                echo '<option value="' . $n . '"' . ($perPage === $n ? ' selected' : '') . '>' . $n . '</option>';
            } ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    </div>
    <?php if ($hasActiveFilters) { ?>
    <div class="col-auto align-self-end">
        <a href="<?php echo htmlspecialchars($clearFiltersUrl); ?>" class="btn btn-outline-secondary btn-sm">Filter zurücksetzen</a>
        <a href="<?php echo htmlspecialchars($clearFiltersUrl); ?>" class="btn btn-outline-secondary btn-sm ms-1">Alles anzeigen</a>
    </div>
    <?php } ?>
</form>

<p class="text-muted small"><?php echo $total; ?> rows<?php if ($totalPages > 1) { ?> · Page <?php echo $pageNum; ?> of <?php echo $totalPages; ?><?php } ?><?php if ($hasActiveFilters) { ?> · <a href="<?php echo htmlspecialchars($clearFiltersUrl); ?>">ohne Filter</a><?php } ?>
<?php
$maximizeQuery = $tableQuery;
$maximizeQuery['maximize'] = '1';
unset($maximizeQuery['transpose']);
$normalQuery = $tableQuery;
unset($normalQuery['maximize']);
unset($normalQuery['transpose']);
$transposeQuery = $tableQuery;
$transposeQuery['transpose'] = '1';
unset($transposeQuery['maximize']);
$maximizeUrl = $tableBase . http_build_query($maximizeQuery, '', '&', PHP_QUERY_RFC3986);
$normalUrl = $tableBase . http_build_query($normalQuery, '', '&', PHP_QUERY_RFC3986);
$transposeUrl = $tableBase . http_build_query($transposeQuery, '', '&', PHP_QUERY_RFC3986);
?>
 · <?php if ($maximize || $transpose) { ?><a href="<?php echo htmlspecialchars($normalUrl); ?>">Normal</a><?php } ?>
<?php if (!$maximize && !$transpose) { ?><a href="<?php echo htmlspecialchars($maximizeUrl); ?>">Maximize</a><?php } ?>
<?php if (!$transpose) { ?> · <a href="<?php echo htmlspecialchars($transposeUrl); ?>">Transpose</a><?php } ?>
</p>

<?php if ($transpose) { ?>
<div class="transpose-rows">
    <?php foreach ($rows as $rowIdx => $r) { ?>
    <div class="card mb-3">
        <div class="card-header small fw-bold d-flex justify-content-between">
            <span>Row <?php echo ($pageNum - 1) * $perPage + $rowIdx + 1; ?></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <tbody>
                    <?php foreach ($headers as $h) {
                        $v = $r->$h ?? '';
                        if ($v instanceof \DateTimeInterface) {
                            $v = $v->format('Y-m-d H:i:s');
                        }
                        $str = (string) $v;
                        echo '<tr><th class="bg-light text-nowrap" style="width:200px">' . htmlspecialchars($h) . '</th>';
                        echo '<td class="text-break" style="white-space:pre-wrap;word-break:break-word">' . htmlspecialchars($str) . '</td></tr>';
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php } ?>
    <?php if (empty($rows)) { ?>
    <p class="text-muted">No rows.</p>
    <?php } ?>
</div>
<?php } else { ?>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <?php foreach ($headers as $h) {
                    $sortUrl = tableSortUrl($tableBase, $tableQuery, $h, $sortDir);
                    $arrow = ($sortCol === $h) ? ($sortDir === 'ASC' ? ' ↑' : ' ↓') : '';
                    echo '<th><a href="' . htmlspecialchars($sortUrl) . '" class="text-decoration-none">' . htmlspecialchars($h) . $arrow . '</a></th>';
                } ?>
            </tr>
            <tr class="table-light">
                <?php foreach ($headers as $h) {
                    $fVal = $filterCols[$h] ?? '';
                    echo '<th class="p-1"><input type="text" class="form-control form-control-sm" name="filter[' . htmlspecialchars($h) . ']" value="' . htmlspecialchars($fVal) . '" placeholder="' . htmlspecialchars($h) . '" form="data-table-filter-form" aria-label="Filter ' . htmlspecialchars($h) . '"></th>';
                } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r) {
                echo '<tr>';
                foreach ($headers as $h) {
                    $v = $r->$h ?? '';
                    if ($v instanceof \DateTimeInterface) {
                        $v = $v->format('Y-m-d H:i:s');
                    }
                    $str = (string) $v;
                    if ($maximize) {
                        echo '<td class="text-break">' . htmlspecialchars($str) . '</td>';
                    } else {
                        echo '<td>' . htmlspecialchars(mb_substr($str, 0, 200)) . (mb_strlen($str) > 200 ? '…' : '') . '</td>';
                    }
                }
                echo '</tr>';
            } ?>
        </tbody>
        <tfoot class="table-light">
            <tr><td colspan="<?php echo count($headers); ?>" class="p-2">
                <button type="submit" form="data-table-filter-form" class="btn btn-sm btn-outline-secondary">Apply column filters</button>
            </td></tr>
        </tfoot>
    </table>
</div>
<?php } ?>

<form id="data-table-filter-form" method="get" action="" class="d-none">
    <input type="hidden" name="page" value="data">
    <input type="hidden" name="action" value="table">
    <input type="hidden" name="table" value="<?php echo htmlspecialchars($table); ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
    <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
    <?php if ($maximize) { ?>
        <input type="hidden" name="maximize" value="1">
    <?php } ?>
    <?php if ($transpose) { ?>
        <input type="hidden" name="transpose" value="1">
    <?php } ?>
    <?php if ($sortCol !== '') { ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortCol); ?>">
        <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDir); ?>">
    <?php } ?>
</form>

<?php if ($totalPages > 1) {
    $pagQuery = $tableQuery;
?>
<nav aria-label="Table pagination" class="mt-3">
    <ul class="pagination flex-wrap">
        <li class="page-item <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">
            <?php if ($pageNum <= 1) { ?>
                <span class="page-link">Previous</span>
            <?php } else {
                $pagQuery['page_num'] = $pageNum - 1;
                $prevUrl = $tableBase . http_build_query($pagQuery, '', '&', PHP_QUERY_RFC3986);
                echo '<a class="page-link" href="' . htmlspecialchars($prevUrl) . '">Previous</a>';
            } ?>
        </li>
        <?php
        $from = max(1, $pageNum - 2);
        $to = min($totalPages, $pageNum + 2);
        for ($i = $from; $i <= $to; $i++) {
            $pagQuery['page_num'] = $i;
            $pageUrl = $tableBase . http_build_query($pagQuery, '', '&', PHP_QUERY_RFC3986);
            ?>
            <li class="page-item <?php echo $i === $pageNum ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars($pageUrl); ?>"><?php echo $i; ?></a>
            </li>
        <?php } ?>
        <li class="page-item <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>">
            <?php if ($pageNum >= $totalPages) { ?>
                <span class="page-link">Next</span>
            <?php } else {
                $pagQuery['page_num'] = $pageNum + 1;
                $nextUrl = $tableBase . http_build_query($pagQuery, '', '&', PHP_QUERY_RFC3986);
                echo '<a class="page-link" href="' . htmlspecialchars($nextUrl) . '">Next</a>';
            } ?>
        </li>
    </ul>
</nav>
<?php } ?>

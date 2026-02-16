<?php
$tableRows = $tableRows ?? [];
$tableSearch = $tableSearch ?? '';
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4">Data</h1>
<p class="text-muted">Table overview with row counts. Click to browse.</p>
<form method="get" action="" class="mb-3">
    <input type="hidden" name="page" value="data">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label for="data-table-search" class="form-label small mb-0">Search table name</label>
            <input type="text" class="form-control form-control-sm" id="data-table-search" name="q" value="<?php echo htmlspecialchars($tableSearch); ?>" placeholder="e.g. objectdata_…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <?php if ($tableSearch !== '') { ?>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>page=data" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            <?php } ?>
        </div>
    </div>
</form>
<div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>Table</th><th>Rows</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($tableRows as $row) {
                $name = $row['name'];
                $count = (int) $row['count'];
                $objectTypeName = $row['objectTypeName'] ?? null;
                $isPrimary = !empty($row['isPrimary']);
                echo '<tr' . ($isPrimary ? ' class="table-warning"' : '') . '>';
                echo '<td><code>' . htmlspecialchars($name) . '</code>';
                if ($objectTypeName !== null && $objectTypeName !== '') {
                    echo ' <span class="text-muted">(' . htmlspecialchars($objectTypeName) . ')</span>';
                }
                if ($isPrimary) {
                    echo ' <span class="badge bg-warning text-dark ms-1" title="Primary object type (Config)">primary</span>';
                }
                echo '</td><td>' . $count . '</td>';
                echo '<td><a href="' . htmlspecialchars($baseUrl . 'page=data&action=table&table=' . urlencode($name)) . '" class="btn btn-sm btn-outline-primary">Browse</a></td></tr>';
            } ?>
        </tbody>
    </table>
</div>
<?php if (empty($tableRows)) { ?>
<p class="text-muted"><?php echo $tableSearch !== '' ? 'No tables match the search.' : 'No tables found or database not configured.'; ?></p>
<?php } ?>

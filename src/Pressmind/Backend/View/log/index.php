<?php
$rows = $rows ?? [];
$type = $type ?? '';
$category = $category ?? '';
$searchQ = $searchQ ?? '';
$pageNum = (int) ($pageNum ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total = (int) ($total ?? 0);
$baseUrl = $baseUrl ?? '?';
$truncateNonce = $truncateNonce ?? '';
?>
<h1 class="h3 mb-4">Logs</h1>

<form method="get" action="" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="logs">
    <div class="col-auto">
        <label for="filter-type" class="form-label small mb-0">Type</label>
        <select name="type" id="filter-type" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="DEBUG" <?php echo $type === 'DEBUG' ? ' selected' : ''; ?>>DEBUG</option>
            <option value="INFO" <?php echo $type === 'INFO' ? ' selected' : ''; ?>>INFO</option>
            <option value="WARNING" <?php echo $type === 'WARNING' ? ' selected' : ''; ?>>WARNING</option>
            <option value="ERROR" <?php echo $type === 'ERROR' ? ' selected' : ''; ?>>ERROR</option>
            <option value="FATAL" <?php echo $type === 'FATAL' ? ' selected' : ''; ?>>FATAL</option>
        </select>
    </div>
    <div class="col-auto">
        <label for="filter-category" class="form-label small mb-0">Category</label>
        <input type="text" class="form-control form-control-sm" id="filter-category" name="category" value="<?php echo htmlspecialchars($category); ?>" placeholder="Category">
    </div>
    <div class="col-auto">
        <label for="filter-q" class="form-label small mb-0">Search in text</label>
        <input type="text" class="form-control form-control-sm" id="filter-q" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Search…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </div>
</form>

<?php
$truncateBase = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$truncateUrl = ($truncateBase !== '' ? $truncateBase : '') . '?page=logs&action=truncate';
?>
<div class="d-flex align-items-center mb-2">
    <p class="text-muted small mb-0 me-3"><?php echo $total; ?> entries</p>
    <?php if ($total > 0) { ?>
    <form method="post" action="<?php echo htmlspecialchars($truncateUrl); ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete ALL log entries? This cannot be undone.');">
        <input type="hidden" name="_pm_nonce" value="<?php echo htmlspecialchars($truncateNonce ?? ''); ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm">Truncate Logs</button>
    </form>
    <?php } ?>
</div>

<div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Category</th><th>Text</th><th>Trace</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r) {
                $trace = $r['trace'] ?? '';
                $text = $r['text'] ?? '';
                $traceCell = $trace !== '' ? '<details><summary>Trace</summary><pre class="small mb-0">' . htmlspecialchars($trace) . '</pre></details>' : '-';
                $logId = (int) $r['id'];
                echo '<tr data-log-id="' . $logId . '" data-log-date="' . htmlspecialchars($r['date']) . '" data-log-type="' . htmlspecialchars($r['type']) . '" data-log-category="' . htmlspecialchars($r['category']) . '">';
                echo '<td>' . $logId . '</td>';
                echo '<td>' . htmlspecialchars($r['date']) . '</td>';
                echo '<td><span class="badge bg-' . ($r['type'] === 'ERROR' || $r['type'] === 'FATAL' ? 'danger' : ($r['type'] === 'WARNING' ? 'warning' : 'secondary')) . '">' . htmlspecialchars($r['type']) . '</span></td>';
                echo '<td>' . htmlspecialchars($r['category']) . '</td>';
                echo '<td>' . htmlspecialchars(mb_substr($text, 0, 200)) . (mb_strlen($text) > 200 ? '…' : '') . '</td>';
                echo '<td>' . $traceCell . '</td>';
                echo '<td><button type="button" class="btn btn-outline-secondary btn-sm log-maximize" data-bs-toggle="modal" data-bs-target="#log-entry-modal" aria-label="Maximize">Maximize</button></td>';
                echo '</tr>';
                echo '<template id="log-fulltext-' . $logId . '">' . htmlspecialchars($text) . '</template>';
                echo '<template id="log-trace-' . $logId . '">' . htmlspecialchars($trace) . '</template>';
            } ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="log-entry-modal" tabindex="-1" aria-labelledby="log-entry-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="log-entry-modal-label">Log entry <span id="log-modal-id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-3">
                    <dt class="col-sm-2">ID</dt><dd class="col-sm-10" id="log-modal-id-val"></dd>
                    <dt class="col-sm-2">Date</dt><dd class="col-sm-10" id="log-modal-date"></dd>
                    <dt class="col-sm-2">Type</dt><dd class="col-sm-10" id="log-modal-type"></dd>
                    <dt class="col-sm-2">Category</dt><dd class="col-sm-10" id="log-modal-category"></dd>
                </dl>
                <div class="mb-3">
                    <h6 class="mb-1">Text</h6>
                    <pre id="log-modal-text" class="mb-0 p-3 bg-light rounded small" style="white-space: pre-wrap; word-break: break-word;"></pre>
                </div>
                <div>
                    <h6 class="mb-1">Trace</h6>
                    <pre id="log-modal-trace" class="mb-0 p-3 bg-light rounded small" style="white-space: pre-wrap; word-break: break-word;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var modal = document.getElementById('log-entry-modal');
    if (!modal) return;
    function getTemplateContent(id) {
        var t = document.getElementById(id);
        if (!t) return '';
        // <template> stores content in a document fragment, not directly in textContent
        if (t.content && t.content.textContent !== undefined) {
            return t.content.textContent;
        }
        return t.textContent || t.innerHTML || '';
    }
    modal.addEventListener('show.bs.modal', function(e) {
        var btn = e.relatedTarget;
        if (!btn || !btn.classList.contains('log-maximize')) return;
        var row = btn.closest('tr');
        if (!row) return;
        var id = row.getAttribute('data-log-id');
        document.getElementById('log-modal-id').textContent = id;
        document.getElementById('log-modal-id-val').textContent = id;
        document.getElementById('log-modal-date').textContent = row.getAttribute('data-log-date') || '';
        document.getElementById('log-modal-type').textContent = row.getAttribute('data-log-type') || '';
        document.getElementById('log-modal-category').textContent = row.getAttribute('data-log-category') || '';
        document.getElementById('log-modal-text').textContent = getTemplateContent('log-fulltext-' + id);
        document.getElementById('log-modal-trace').textContent = getTemplateContent('log-trace-' + id);
    });
})();
</script>

<?php if ($totalPages > 1) {
    $navBase = $baseUrl . 'page_num=';
?>
<nav aria-label="Log pagination" class="mt-3">
    <ul class="pagination">
        <li class="page-item <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum <= 1 ? '#' : htmlspecialchars($navBase . ($pageNum - 1)); ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= min($totalPages, 20); $i++) { ?>
            <li class="page-item <?php echo $i === $pageNum ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars($navBase . $i); ?>"><?php echo $i; ?></a>
            </li>
        <?php } ?>
        <li class="page-item <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum >= $totalPages ? '#' : htmlspecialchars($navBase . ($pageNum + 1)); ?>">Next</a>
        </li>
    </ul>
</nav>
<?php } ?>

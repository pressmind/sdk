<?php
$title = $title ?? 'Documents';
$error = $error ?? null;
$documents = $documents ?? [];
$collection = $collection ?? '';
$baseUrl = $baseUrl ?? '';
$total = (int) ($total ?? 0);
$pageNum = (int) ($pageNum ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$perPage = (int) ($perPage ?? 50);
$paginationBaseUrl = $paginationBaseUrl ?? '';
$filterQuery = $filterQuery ?? '';

/**
 * Extract displayable _id and link info from a document.
 */
function _extractDocId(array $doc): array
{
    $raw = $doc['_id'] ?? null;
    $display = '—';
    $forLink = '';
    $by = '';
    if ($raw !== null) {
        if (is_array($raw) && isset($raw['$oid'])) {
            $display = $raw['$oid'];
            $forLink = $display;
            $by = '_id';
        } else {
            $display = (string) $raw;
            $forLink = $display;
            $by = is_numeric($raw) ? '' : '_id';
        }
    }
    return ['display' => $display, 'forLink' => $forLink, 'by' => $by];
}
?>
<style>
.doc-card .json-view { max-height: 180px; overflow: hidden; position: relative; cursor: pointer; }
.doc-card .json-view.expanded { max-height: none; overflow: auto; }
.doc-card .doc-fade { position: absolute; bottom: 0; left: 0; right: 0; height: 28px; background: linear-gradient(transparent, #282c34); pointer-events: none; }
.doc-card .doc-fade.hidden { display: none; }
</style>
<h1 class="h3 mb-4"><?php echo htmlspecialchars($title); ?></h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=collections">Collections</a> · <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search">Search</a></p>
<?php if ($error !== null) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php } ?>
<?php if ($collection !== '') { ?>
<p class="mb-2">Collection: <code><?php echo htmlspecialchars($collection); ?></code></p>
<form method="get" action="" class="mb-3">
    <input type="hidden" name="page" value="search">
    <input type="hidden" name="action" value="query">
    <input type="hidden" name="format" value="html">
    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collection); ?>">
    <div class="row g-2 align-items-end">
        <div class="col">
            <label for="mongo-filter" class="form-label small mb-0">Query</label>
            <input type="text" class="form-control form-control-sm font-monospace" id="mongo-filter" name="filter" value="<?php echo htmlspecialchars($filterQuery); ?>" placeholder='{id_media_object: 12345}'>
        </div>
        <div class="col-auto">
            <label for="mongo-pp" class="form-label small mb-0">Per page</label>
            <select name="per_page" id="mongo-pp" class="form-select form-select-sm">
                <?php foreach ([25, 50, 100] as $n) {
                    echo '<option value="' . $n . '"' . ($perPage === $n ? ' selected' : '') . '>' . $n . '</option>';
                } ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-success btn-sm">Find</button>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=query&format=html&collection=<?php echo urlencode($collection); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
    <p class="text-muted small mt-1 mb-0">MongoDB query syntax, e.g. <code>{id_media_object: 12345}</code> or <code>{transport_type: "BUS"}</code>. Leave empty for all documents.</p>
</form>
<?php if ($error === null) { ?>
<p class="text-muted small mb-3">
    <?php
    $rangeFrom = ($pageNum - 1) * $perPage + 1;
    $rangeTo = min($pageNum * $perPage, $total);
    if ($total > 0) {
        echo $rangeFrom . ' – ' . $rangeTo . ' of ' . $total;
    } else {
        echo '0 documents';
    }
    ?>
</p>
<?php if (!empty($documents)) { ?>
<div class="mongo-documents">
    <?php foreach ($documents as $docIdx => $doc) {
        $idInfo = _extractDocId($doc);
        $docJson = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>
    <div class="card mb-2 doc-card">
        <div class="position-relative" id="doc-wrap-<?php echo $docIdx; ?>">
            <div class="json-view" id="doc-<?php echo $docIdx; ?>" data-no-toolbar data-json="<?php echo htmlspecialchars($docJson, ENT_QUOTES, 'UTF-8'); ?>" data-depth="1" data-theme="dark" onclick="toggleDocCard(<?php echo $docIdx; ?>)"></div>
            <div class="doc-fade" id="doc-fade-<?php echo $docIdx; ?>"></div>
        </div>
        <div class="card-footer p-1 small bg-light d-flex justify-content-between align-items-center">
            <span class="text-muted font-monospace" style="font-size:0.78rem">_id: <?php echo htmlspecialchars($idInfo['display']); ?></span>
            <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=detail&collection=<?php echo urlencode($collection); ?>&id=<?php echo urlencode($idInfo['forLink']); ?><?php echo $idInfo['by'] !== '' ? '&by=' . urlencode($idInfo['by']) : ''; ?>" class="btn btn-outline-primary btn-sm py-0">Detail</a>
        </div>
    </div>
    <?php } ?>
</div>
<?php if ($totalPages > 1) {
    $navBase = $paginationBaseUrl . 'page_num=';
?>
<nav aria-label="Documents pagination" class="mt-3">
    <ul class="pagination flex-wrap">
        <li class="page-item <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum <= 1 ? '#' : htmlspecialchars($navBase . ($pageNum - 1)); ?>">Previous</a>
        </li>
        <?php $rangeFrom = max(1, $pageNum - 2); $rangeTo = min($totalPages, $pageNum + 2);
        for ($i = $rangeFrom; $i <= $rangeTo; $i++) { ?>
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
<?php } else { ?>
<p class="text-muted">No documents found.</p>
<?php } ?>
<?php } ?>
<?php } ?>
<script>
function toggleDocCard(idx) {
    var jv = document.getElementById('doc-' + idx);
    var fade = document.getElementById('doc-fade-' + idx);
    if (!jv) return;
    var isExpanded = jv.classList.contains('expanded');
    if (isExpanded) {
        jv.classList.remove('expanded');
        if (fade) fade.classList.remove('hidden');
    } else {
        jv.classList.add('expanded');
        if (fade) fade.classList.add('hidden');
    }
}
</script>

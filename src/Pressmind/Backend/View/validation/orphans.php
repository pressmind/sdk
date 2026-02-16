<?php
$orphans = $orphans ?? [];
$totalMOs = (int) ($totalMOs ?? 0);
$orphanCount = (int) ($orphanCount ?? 0);
$okCount = (int) ($okCount ?? 0);
$baseUrl = $baseUrl ?? '?';
$searchQ = $searchQ ?? '';
$pageNum = (int) ($pageNum ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$total = (int) ($total ?? 0);
$paginationBaseUrl = $paginationBaseUrl ?? $baseUrl . 'page=validation&action=orphans&';
?>
<h1 class="h3 mb-4">Touristic Orphans</h1>
<p class="text-muted">Visible Media Objects without Cheapest Price entries (not bookable in search).</p>
<form method="get" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="validation">
    <input type="hidden" name="action" value="orphans">
    <div class="col-auto">
        <label for="orphans-q" class="form-label small mb-0">Search (ID, code, name)</label>
        <input type="text" class="form-control form-control-sm" id="orphans-q" name="q" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Search…">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    </div>
</form>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">Total MOs</h6><p class="h4 mb-0"><?php echo $totalMOs; ?></p></div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">Orphans</h6><p class="h4 mb-0 text-warning"><?php echo $orphanCount; ?></p></div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body"><h6 class="text-muted">OK</h6><p class="h4 mb-0 text-success"><?php echo $okCount; ?></p></div></div>
    </div>
</div>
<p class="text-muted small"><?php echo $orphanCount; ?> orphans<?php if ($totalPages > 1) { ?> · Page <?php echo $pageNum; ?> of <?php echo $totalPages; ?><?php } ?></p>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Object Type</th><th>Booking Packages</th><th>Dates</th></tr></thead>
        <tbody>
            <?php foreach ($orphans as $o) {
                echo '<tr><td>' . (int) ($o->id ?? 0) . '</td><td>' . htmlspecialchars($o->code ?? '') . '</td><td>' . htmlspecialchars(mb_substr($o->name ?? '', 0, 50)) . '</td><td>' . (int) ($o->id_object_type ?? 0) . '</td><td>' . (int) ($o->booking_packages_count ?? 0) . '</td><td>' . (int) ($o->dates_count ?? 0) . '</td></tr>';
            } ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages > 1) {
    $navBase = $paginationBaseUrl . 'page_num=';
?>
<nav aria-label="Orphans pagination" class="mt-3">
    <ul class="pagination flex-wrap">
        <li class="page-item <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum <= 1 ? '#' : htmlspecialchars($navBase . ($pageNum - 1)); ?>">Previous</a>
        </li>
        <?php $from = max(1, $pageNum - 2); $to = min($totalPages, $pageNum + 2);
        for ($i = $from; $i <= $to; $i++) { ?>
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
<?php if (empty($orphans) && $orphanCount === 0) { ?>
<p class="text-muted">No orphans found.</p>
<?php } ?>

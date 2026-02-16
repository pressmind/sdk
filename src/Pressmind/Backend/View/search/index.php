<?php
$title = $title ?? 'Search';
$mongoConfigured = $mongoConfigured ?? false;
$baseUrl = $baseUrl ?? '';
?>
<h1><?php echo htmlspecialchars($title); ?></h1>
<?php if (!$mongoConfigured) { ?>
<p class="text-warning">MongoDB is not configured or disabled (check <code>data.search_mongodb</code>).</p>
<?php } else { ?>
<ul class="nav flex-column mb-4">
    <li class="nav-item"><a class="nav-link" href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=collections">Collections &amp; Indexes</a></li>
</ul>
<h2 class="h5">Query</h2>
<form method="get" action="" class="mb-3">
    <input type="hidden" name="page" value="search" />
    <input type="hidden" name="action" value="query" />
    <input type="hidden" name="format" value="html" />
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-0">Collection</label>
            <input type="text" name="collection" class="form-control form-control-sm" placeholder="e.g. best_price_search_based_de_0" required />
        </div>
        <div class="col">
            <label class="form-label small mb-0">Query</label>
            <input type="text" name="filter" class="form-control form-control-sm font-monospace" placeholder='{id_media_object: 12345}' />
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-success btn-sm">Find</button>
        </div>
    </div>
    <p class="text-muted small mt-1 mb-0">MongoDB query syntax. Leave query empty for all documents.</p>
</form>
<?php } ?>

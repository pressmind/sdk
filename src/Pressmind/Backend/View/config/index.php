<?php
$sections = $sections ?? [];
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4">Config</h1>
<p class="text-muted">View configuration by section. Current environment only (from Registry).</p>
<ul class="nav nav-tabs" role="tablist">
    <?php foreach ($sections as $key => $sec) {
        echo '<li class="nav-item"><a class="nav-link' . ($sec['has_data'] ? '' : ' text-muted') . '" href="' . htmlspecialchars($baseUrl . 'page=config&action=section&section=' . urlencode($key)) . '">' . htmlspecialchars($sec['label']) . '</a></li>';
    } ?>
</ul>
<p class="mt-3">
    <a href="<?php echo htmlspecialchars($baseUrl); ?>page=config&action=raw" class="btn btn-outline-secondary">Raw JSON</a>
    <a href="<?php echo htmlspecialchars($baseUrl); ?>page=config&action=diff" class="btn btn-outline-secondary ms-2">Diff (e.g. Dev vs Prod)</a>
</p>

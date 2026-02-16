<?php
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4">Validierung</h1>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><a href="<?php echo htmlspecialchars($baseUrl); ?>page=validation&action=orphans">Touristic Orphans</a></h5>
                <p class="card-text small text-muted">Media Objects ohne Cheapest Prices (sichtbar, aber nicht buchbar).</p>
            </div>
        </div>
    </div>
</div>

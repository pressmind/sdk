<?php
$stats = $stats ?? ['pictures' => ['total' => 0, 'exists' => 0, 'missing' => 0], 'sections' => ['total' => 0, 'exists' => 0, 'missing' => 0], 'documents' => ['total' => 0, 'exists' => 0, 'missing' => 0]];
$derivativeSummary = $derivativeSummary ?? [];
$totalChecked = (int) ($totalChecked ?? 0);
$totalExists = (int) ($totalExists ?? 0);
$totalMissing = (int) ($totalMissing ?? 0);
$totalSizeFormatted = $totalSizeFormatted ?? '0 B';
$baseUrl = $baseUrl ?? '?';
$totalMissingAll = $stats['pictures']['missing'] + $stats['sections']['missing'] + $stats['documents']['missing'];
?>
<h1 class="h3 mb-4">Image Cache</h1>
<p class="text-muted small">Verification checks derivative files on storage (Pictures, Sections, DocumentMediaObjects). Based on the same report as the CLI image processor.</p>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Checked images</h6>
                <p class="card-title h4 mb-0"><?php echo number_format($totalChecked, 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Existing</h6>
                <p class="card-title h4 mb-0 text-success"><?php echo number_format($totalExists, 0, ',', '.'); ?></p>
                <?php if ($totalChecked > 0) { ?>
                    <p class="card-text small text-muted mb-0"><?php echo number_format(($totalExists / $totalChecked) * 100, 1); ?>%</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Missing</h6>
                <p class="card-title h4 mb-0 <?php echo $totalMissing > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($totalMissing, 0, ',', '.'); ?></p>
                <?php if ($totalChecked > 0) { ?>
                    <p class="card-text small text-muted mb-0"><?php echo number_format(($totalMissing / $totalChecked) * 100, 1); ?>%</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Total size</h6>
                <p class="card-title h4 mb-0"><?php echo htmlspecialchars($totalSizeFormatted); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Detailed statistics</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Type</th><th>Checked</th><th>Existing</th><th>Missing</th></tr></thead>
            <tbody>
                <tr>
                    <td>Pictures</td>
                    <td><?php echo number_format($stats['pictures']['total'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['pictures']['exists'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['pictures']['missing'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td>Sections</td>
                    <td><?php echo number_format($stats['sections']['total'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['sections']['exists'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['sections']['missing'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td>DocumentMediaObjects</td>
                    <td><?php echo number_format($stats['documents']['total'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['documents']['exists'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($stats['documents']['missing'], 0, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($derivativeSummary)) { ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Derivative summary</span>
        <input type="text" class="form-control form-control-sm" id="imagecache-derivative-search" placeholder="Search…" aria-label="Search derivatives" style="max-width: 200px;">
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 table-striped" id="imagecache-derivative-table">
            <thead><tr><th>Type</th><th>Name</th><th>Extension</th><th>Existing / Total</th><th>%</th><th>Total size</th><th>Average size</th></tr></thead>
            <tbody>
                <?php foreach ($derivativeSummary as $row) {
                    $rowSearch = $row['type'] . ' ' . $row['name'] . ' ' . $row['extension'] . ' ' . $row['exists_count'] . ' ' . $row['total_count'];
                    echo '<tr data-search="' . htmlspecialchars($rowSearch) . '">';
                    echo '<td>' . htmlspecialchars($row['type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['extension']) . '</td>';
                    echo '<td>' . number_format($row['exists_count'], 0, ',', '.') . ' / ' . number_format($row['total_count'], 0, ',', '.') . '</td>';
                    echo '<td>' . number_format($row['percentage'], 1) . '%</td>';
                    echo '<td>' . htmlspecialchars($row['total_size_formatted'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['avg_size_formatted']) . '</td>';
                    echo '</tr>';
                } ?>
            </tbody>
        </table>
    </div>
</div>
<script>(function(){var i=document.getElementById('imagecache-derivative-search');var t=document.querySelector('#imagecache-derivative-table tbody');if(i&&t){i.addEventListener('input',function(){var q=(this.value||'').toLowerCase();t.querySelectorAll('tr').forEach(function(r){var s=(r.getAttribute('data-search')||'').toLowerCase();r.style.display=q===''||s.indexOf(q)!==-1?'':'none';});});}})();</script>
<?php } ?>

<?php if ($totalMissingAll > 0) {
    $missingRows = [];
    foreach (['pictures' => 'Picture', 'sections' => 'Section', 'documents' => 'Document'] as $key => $typeLabel) {
        foreach ($stats[$key]['missing_list'] as $item) {
            $missingRows[] = ['type' => $typeLabel, 'id' => $item['id'], 'id_media_object' => $item['id_media_object'], 'file_name' => $item['file_name'], 'section_name' => $item['section_name'] ?? '', 'id_step' => $item['id_step'] ?? ''];
        }
    }
    $missingShown = count($missingRows);
    $missingCapped = $missingShown < $totalMissingAll;
?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Missing images (<?php echo $totalMissingAll; ?>)<?php if ($missingCapped) { ?> <span class="text-muted small">— showing first <?php echo number_format($missingShown, 0, ',', '.'); ?></span><?php } ?></span>
        <input type="text" class="form-control form-control-sm" id="imagecache-missing-search" placeholder="Search…" aria-label="Search missing">
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 table-striped" id="imagecache-missing-table">
            <thead><tr><th>Type</th><th>ID</th><th>Media Object</th><th>File name</th><th>Section / Step</th></tr></thead>
            <tbody>
                <?php foreach ($missingRows as $item) {
                    $rowSearch = $item['type'] . ' ' . $item['id'] . ' ' . $item['id_media_object'] . ' ' . $item['file_name'] . ' ' . $item['section_name'] . ' ' . $item['id_step'];
                    echo '<tr data-search="' . htmlspecialchars($rowSearch) . '">';
                    echo '<td>' . htmlspecialchars($item['type']) . '</td>';
                    echo '<td>' . (int) $item['id'] . '</td>';
                    echo '<td>' . htmlspecialchars((string) $item['id_media_object']) . '</td>';
                    echo '<td>' . htmlspecialchars(mb_substr($item['file_name'], 0, 60)) . (mb_strlen($item['file_name']) > 60 ? '…' : '') . '</td>';
                    echo '<td>' . htmlspecialchars($item['section_name'] !== '' ? $item['section_name'] : (string) $item['id_step']) . '</td>';
                    echo '</tr>';
                } ?>
            </tbody>
        </table>
    </div>
</div>
<script>(function(){var i=document.getElementById('imagecache-missing-search');var t=document.querySelector('#imagecache-missing-table tbody');if(i&&t){i.addEventListener('input',function(){var q=(this.value||'').toLowerCase();t.querySelectorAll('tr').forEach(function(r){var s=(r.getAttribute('data-search')||'').toLowerCase();r.style.display=q===''||s.indexOf(q)!==-1?'':'none';});});}})();</script>
<?php } else { ?>
<div class="card mb-4">
    <div class="card-body">
        <p class="text-success mb-0">All images successfully downloaded and present.</p>
    </div>
</div>
<?php } ?>

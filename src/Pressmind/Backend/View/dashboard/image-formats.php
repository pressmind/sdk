<?php
$imageFormats = $imageFormats ?? [];
$baseUrl = $baseUrl ?? '?';
?>
<h1 class="h3 mb-4">Image Formats</h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=dashboard">&larr; Back to Dashboard</a></p>
<div class="card">
    <div class="card-header">Image Formats (Derivatives)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Name</th><th>Image Ratio</th><th>Max Width</th><th>Max Height</th><th>Crop</th><th>Preserve Aspect</th></tr></thead>
            <tbody>
                <?php
                if (empty($imageFormats)) {
                    echo '<tr><td colspan="6" class="text-muted">No derivatives configured</td></tr>';
                } else {
                    foreach ($imageFormats as $name => $cfg) {
                        if (!is_array($cfg) || strpos($name, 'EXAMPLE') !== false) {
                            continue;
                        }
                        $w = isset($cfg['max_width']) ? (int) $cfg['max_width'] : null;
                        $h = isset($cfg['max_height']) ? (int) $cfg['max_height'] : null;
                        $ratioLabel = '-';
                        if ($w !== null && $h !== null && $h > 0) {
                            $ratio = round($w / $h, 2);
                            $ratioLabel = $ratio . ' <span class="text-muted">(' . $w . '×' . $h . ')</span>';
                        }
                        echo '<tr><td>' . htmlspecialchars($name) . '</td><td>' . $ratioLabel . '</td><td>' . htmlspecialchars($cfg['max_width'] ?? '-') . '</td><td>' . htmlspecialchars($cfg['max_height'] ?? '-') . '</td><td>' . (!empty($cfg['crop']) ? 'Yes' : 'No') . '</td><td>' . (!empty($cfg['preserve_aspect_ratio']) ? 'Yes' : 'No') . '</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

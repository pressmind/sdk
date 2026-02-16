<?php
$envs = $envs ?? ['development' => 'Development', 'production' => 'Production', 'testing' => 'Testing'];
$leftKey = $leftKey ?? 'development';
$rightKey = $rightKey ?? 'production';
$diff = $diff ?? [];
$error = $error ?? null;
$baseUrl = $baseUrl ?? '?';

function _configDiffValue($val) {
    if ($val === null) {
        return '<em class="text-muted">—</em>';
    }
    if (is_bool($val)) {
        return $val ? 'true' : 'false';
    }
    if (is_array($val)) {
        return '<code class="small">' . htmlspecialchars(json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</code>';
    }
    return htmlspecialchars((string) $val);
}
?>
<h1 class="h3 mb-4">Config Diff</h1>
<p class="text-muted">Compare configuration between two environments (e.g. Development vs Production).</p>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=config">&larr; Back to Config</a></p>

<?php if ($error !== null) { ?>
<div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php } else { ?>

<form method="get" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="page" value="config">
    <input type="hidden" name="action" value="diff">
    <div class="col-auto">
        <label for="diff-left" class="form-label small mb-0">Left</label>
        <select name="left" id="diff-left" class="form-select form-select-sm">
            <?php foreach ($envs as $k => $label) {
                echo '<option value="' . htmlspecialchars($k) . '"' . ($k === $leftKey ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
            } ?>
        </select>
    </div>
    <div class="col-auto">
        <label for="diff-right" class="form-label small mb-0">Right</label>
        <select name="right" id="diff-right" class="form-select form-select-sm">
            <?php foreach ($envs as $k => $label) {
                echo '<option value="' . htmlspecialchars($k) . '"' . ($k === $rightKey ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
            } ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Compare</button>
    </div>
</form>

<?php if (empty($diff)) { ?>
<p class="text-muted">No differences (configs are identical for the selected environments).</p>
<?php } else { ?>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Path</th>
                <th>Status</th>
                <th><?php echo htmlspecialchars(ucfirst($leftKey)); ?> (left)</th>
                <th><?php echo htmlspecialchars(ucfirst($rightKey)); ?> (right)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($diff as $path => $row) {
                $status = $row['status'] ?? 'changed';
                $statusLabel = $status === 'only_left' ? 'Only in left' : ($status === 'only_right' ? 'Only in right' : 'Changed');
                $statusClass = $status === 'only_left' ? 'info' : ($status === 'only_right' ? 'success' : 'warning');
                echo '<tr>';
                echo '<td><code class="small">' . htmlspecialchars($path) . '</code></td>';
                echo '<td><span class="badge bg-' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</span></td>';
                echo '<td class="text-break">' . _configDiffValue($row['left'] ?? null) . '</td>';
                echo '<td class="text-break">' . _configDiffValue($row['right'] ?? null) . '</td>';
                echo '</tr>';
            } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php } ?>

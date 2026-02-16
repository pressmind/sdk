<?php
$commands = $commands ?? [];
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4">Commands</h1>
<p class="text-muted">Run CLI commands via the backend. Use <a href="<?php echo htmlspecialchars($baseUrl); ?>page=commands&action=execute">Execute</a> for live output.</p>
<div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>Command</th><th>Description</th><th>Arguments</th><th>Danger</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($commands as $name => $def) {
                $danger = $def['danger'] ?? 'low';
                $badge = $danger === 'critical' ? 'danger' : ($danger === 'high' ? 'warning' : ($danger === 'medium' ? 'info' : 'secondary'));
                $args = array_column($def['arguments'] ?? [], 'name');
                ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($name); ?></code></td>
                    <td><?php echo htmlspecialchars($def['description'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $args)); ?></td>
                    <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($danger); ?></span></td>
                    <td><a href="<?php echo htmlspecialchars($baseUrl); ?>page=commands&action=execute&command=<?php echo urlencode($name); ?>" class="btn btn-sm btn-outline-primary">Run</a></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

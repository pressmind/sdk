<?php
/**
 * Terminal-style log container for SSE output. Use with ProcessStream JS.
 * Vars: $logContainerId = 'process-log', $title = 'Output'
 */
$logContainerId = $logContainerId ?? 'process-log';
$title = $title ?? 'Output';
?>
<div class="card bg-dark text-light">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?php echo htmlspecialchars($title); ?></span>
        <span class="badge bg-secondary" id="<?php echo htmlspecialchars($logContainerId); ?>-status">Waiting…</span>
    </div>
    <div class="card-body p-0">
        <pre id="<?php echo htmlspecialchars($logContainerId); ?>" class="mb-0 p-3 small" style="max-height: 400px; overflow-y: auto; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-all;"></pre>
    </div>
</div>

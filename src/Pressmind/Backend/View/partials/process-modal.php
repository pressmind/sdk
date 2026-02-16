<?php
/**
 * Reusable Bootstrap modal for SSE process output (command/import).
 * Use with ProcessStream JS: pass modal's log container id to ProcessStream and call start(url).
 * Vars: $modalId = 'process-modal', $logContainerId = 'process-modal-log', $title = 'Output', $showAbort = true
 */
$modalId = $modalId ?? 'process-modal';
$logContainerId = $logContainerId ?? 'process-modal-log';
$title = $title ?? 'Output';
$showAbort = $showAbort ?? true;
$statusId = $logContainerId . '-status';
?>
<div class="modal fade" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span id="<?php echo htmlspecialchars($modalId); ?>-title"><?php echo htmlspecialchars($title); ?></span>
                    <span class="badge bg-secondary" id="<?php echo htmlspecialchars($statusId); ?>">Waiting…</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <pre id="<?php echo htmlspecialchars($logContainerId); ?>" class="mb-0 p-3 small bg-dark text-light" style="max-height: 400px; overflow-y: auto; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-all;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="<?php echo htmlspecialchars($modalId); ?>-close" data-bs-dismiss="modal" disabled>Close</button>
                <?php if ($showAbort) { ?>
                <button type="button" class="btn btn-warning" id="<?php echo htmlspecialchars($modalId); ?>-abort">Abort</button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php
$id = (int) ($id ?? 0);
$detail = $detail ?? [];
$baseUrl = $baseUrl ?? '?';
$streamBase = $streamBase ?? $baseUrl . 'page=import&action=stream';
$error = $detail['error'] ?? null;
$diagnosis = $detail['diagnosis'] ?? [];
?>
<h1 class="h3 mb-4">Orphan Detail <?php echo $id; ?></h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=validation&action=orphans">&larr; Back to Orphans</a> | <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&id_media_object=<?php echo $id; ?>">Suche (MongoDB)</a></p>
<?php if ($error) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php return;
} ?>
<?php if (!empty($diagnosis['issues'])) { ?>
<div class="card mb-3"><div class="card-header">Issues</div><ul class="list-group list-group-flush"><?php foreach ($diagnosis['issues'] as $i) { echo '<li class="list-group-item">' . htmlspecialchars($i) . '</li>'; } ?></ul></div>
<?php } ?>
<?php if (!empty($diagnosis['recommendations'])) { ?>
<div class="card mb-3"><div class="card-header">Recommendations</div><ul class="list-group list-group-flush"><?php foreach ($diagnosis['recommendations'] as $r) { echo '<li class="list-group-item">' . htmlspecialchars($r) . '</li>'; } ?></ul></div>
<?php } ?>
<p>
    <button type="button" class="btn btn-primary" id="btn-reimport">Re-Import this MO</button>
</p>
<?php
include __DIR__ . '/../partials/process-modal.php';
include __DIR__ . '/../partials/process-stream.php';
?>
<script>
(function() {
    var streamBase = <?php echo json_encode($streamBase); ?>;
    var id = <?php echo (int) $id; ?>;
    var modalEl = document.getElementById('process-modal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var stream = new ProcessStream('process-modal-log', {
        statusId: 'process-modal-log-status',
        closeBtnId: 'process-modal-close',
        abortBtnId: 'process-modal-abort',
        onComplete: function() {
            if (modal) document.getElementById('process-modal-close').disabled = false;
        }
    });
    document.getElementById('btn-reimport').addEventListener('click', function() {
        document.getElementById('process-modal-close').disabled = true;
        document.getElementById('process-modal-log').innerHTML = '';
        if (modal) modal.show();
        stream.start(streamBase + '&command=' + encodeURIComponent('import mediaobject') + '&ids=' + id);
    });
    document.getElementById('process-modal-abort').addEventListener('click', function() {
        stream.abort();
    });
})();
</script>

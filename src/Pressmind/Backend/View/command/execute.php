<?php
$command = $command ?? null;
$commandName = $commandName ?? '';
$allCommands = $allCommands ?? [];
$streamNonce = $streamNonce ?? '';
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
$streamBase = $baseUrl . 'page=commands&action=stream&_pm_nonce=' . urlencode($streamNonce);
?>
<h1 class="h3 mb-4">Execute Command</h1>

<form method="get" action="" class="mb-4" id="cmd-form">
    <input type="hidden" name="page" value="commands">
    <input type="hidden" name="action" value="execute">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label for="cmd" class="form-label">Command</label>
            <select name="command" id="cmd" class="form-select">
                <?php foreach ($allCommands as $name => $def) {
                    echo '<option value="' . htmlspecialchars($name) . '"' . ($name === $commandName ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
                } ?>
            </select>
        </div>
        <?php if ($command !== null && !empty($command['arguments'])) {
            foreach ($command['arguments'] as $arg) {
                $key = $arg['name'];
                if (strpos($key, '--') === 0) {
                    continue;
                }
                $val = isset($_GET[$key]) ? $_GET[$key] : '';
                ?>
                <div class="col-auto">
                    <label for="arg-<?php echo htmlspecialchars($key); ?>" class="form-label"><?php echo htmlspecialchars($arg['label'] ?? $key); ?></label>
                    <input type="text" class="form-control arg-input" id="arg-<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" data-arg="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($val); ?>" placeholder="<?php echo htmlspecialchars($arg['label'] ?? ''); ?>">
                </div>
            <?php }
        } ?>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary">Select</button>
        </div>
    </div>
</form>

<?php if ($command !== null) {
    include __DIR__ . '/../partials/process-stream.php';
    $description = $command['description'] ?? '';
    $danger = $command['danger'] ?? 'low';
    $dangerLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
?>
<?php if ($description !== '') { ?>
<div class="alert alert-secondary mb-3" role="region" aria-label="Command description">
    <strong><?php echo htmlspecialchars($commandName); ?></strong>
    <?php if (isset($dangerLabels[$danger])) { ?>
        <span class="badge bg-<?php echo $danger === 'critical' ? 'danger' : ($danger === 'high' ? 'warning' : ($danger === 'medium' ? 'info' : 'secondary')); ?> ms-2"><?php echo htmlspecialchars($dangerLabels[$danger]); ?></span>
    <?php } ?>
    <p class="mb-0 mt-1"><?php echo htmlspecialchars($description); ?></p>
</div>
<?php } ?>
<div class="card bg-dark text-light">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?php echo htmlspecialchars($commandName); ?></span>
        <span class="badge bg-secondary" id="process-log-status">Ready</span>
    </div>
    <div class="card-body p-0">
        <pre id="process-log" class="mb-0 p-3 small" style="max-height: 400px; overflow-y: auto; font-family: ui-monospace, monospace; white-space: pre-wrap; word-break: break-all;"></pre>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-primary" id="btn-start">Start</button>
        <button type="button" class="btn btn-outline-secondary d-none" id="btn-close">Close</button>
        <button type="button" class="btn btn-warning" id="btn-abort" disabled title="Unterbrochenen Prozess beenden">Prozess abbrechen</button>
    </div>
</div>

<script>
(function() {
    var streamBase = <?php echo json_encode($streamBase); ?>;
    function buildStreamUrl() {
        var cmd = document.getElementById('cmd');
        var url = streamBase + '&command=' + encodeURIComponent(cmd ? cmd.value : '');
        document.querySelectorAll('.arg-input').forEach(function(inp) {
            var k = inp.getAttribute('data-arg');
            var v = inp.value.trim();
            if (k && v) url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
        });
        return url;
    }
    var stream = new ProcessStream('process-log', {
        statusId: 'process-log-status',
        startBtnId: 'btn-start',
        closeBtnId: 'btn-close',
        abortBtnId: 'btn-abort'
    });
    var abortBtn = document.getElementById('btn-abort');
    document.getElementById('btn-start').addEventListener('click', function() {
        stream.start(buildStreamUrl());
        if (abortBtn) abortBtn.disabled = false;
    });
    document.getElementById('btn-close').addEventListener('click', function() {
        stream.reset();
    });
    abortBtn.addEventListener('click', function() {
        stream.abort();
        this.disabled = true;
    });
})();
</script>
<?php } ?>

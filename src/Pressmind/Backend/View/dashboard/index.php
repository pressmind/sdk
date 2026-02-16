<?php
$systemInfo = $systemInfo ?? [];
$lastErrors = $lastErrors ?? [];
$importLock = $importLock ?? null;
$queueCount = $queueCount ?? 0;
$processList = $processList ?? [];
$mediaObjectStats = $mediaObjectStats ?? [];
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
$processCount = count($processList);
$processListTableUrl = $baseUrl . 'page=data&action=table&table=pmt2core_process_list';
$configEnv = $configEnv ?? '-';
$imageCacheMissing = (int) ($imageCacheMissing ?? 0);
?>
<h1 class="h3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>Dashboard</span>
    <span class="h6 text-muted fw-normal mb-0 d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-<?php echo $configEnv === 'PROD' ? 'success' : 'secondary'; ?>" title="Loaded config environment"><?php echo htmlspecialchars($configEnv); ?></span>
        <span title="Server time"><?php echo htmlspecialchars(date('Y-m-d H:i:s T')); ?></span>
    </span>
</h1>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Media Objects</h6>
                <p class="card-title h4 mb-0"><?php echo array_sum(array_column($mediaObjectStats, 'count')); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Import Queue</h6>
                <p class="card-title h4 mb-0"><?php echo (int) $queueCount; ?></p>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>page=commands" class="small">Commands</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Process List (Queue)</h6>
                <p class="card-title h4 mb-0"><?php echo $processCount; ?> <?php echo $processCount === 1 ? 'Prozess läuft' : 'Prozesse laufen'; ?></p>
                <a href="<?php echo htmlspecialchars($processListTableUrl); ?>" class="small">Detail / Tabelle</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Import Lock</h6>
                <p class="card-title h5 mb-0"><?php echo $importLock ? 'Active (PID ' . (int) $importLock['pid'] . ')' : 'None'; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">Image Cache</h6>
                <p class="card-title h4 mb-0 <?php echo $imageCacheMissing > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $imageCacheMissing; ?> Missing</p>
                <p class="card-text small text-muted mb-0">Not processed</p>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>page=imagecache" class="small">Detail</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-subtitle mb-1 text-muted">PHP</h6>
                <p class="card-title h5 mb-0"><?php echo htmlspecialchars($systemInfo['php_version'] ?? PHP_VERSION); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">System Info</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">APP Environment</dt>
            <dd class="col-sm-9"><span class="badge bg-<?php echo $configEnv === 'PROD' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($configEnv); ?></span></dd>
            <dt class="col-sm-3">SDK Version</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['sdk_version'] ?? '-'); ?><?php if (!empty($systemInfo['sdk_date']) && $systemInfo['sdk_date'] !== '-') { ?> <span class="text-muted small">(<?php echo htmlspecialchars($systemInfo['sdk_date']); ?>)</span><?php } ?></dd>
            <dt class="col-sm-3">PHP Version</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['php_version'] ?? PHP_VERSION); ?></dd>
            <dt class="col-sm-3">Server IP</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['server_ip'] ?? '-'); ?></dd>
            <dt class="col-sm-3">DB Name</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['db_name'] ?? '-'); ?></dd>
            <dt class="col-sm-3">DB Host</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['db_host'] ?? '-'); ?></dd>
            <dt class="col-sm-3">Redis</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['redis'] ?? 'unknown'); ?></dd>
            <dt class="col-sm-3">MongoDB Search</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($systemInfo['mongodb'] ?? 'unknown'); ?></dd>
        </dl>
    </div>
</div>

<?php if (!empty($processList)) { ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Process List – <?php echo $processCount; ?> <?php echo $processCount === 1 ? 'Prozess läuft' : 'Prozesse laufen'; ?></span>
        <a href="<?php echo htmlspecialchars($processListTableUrl); ?>" class="btn btn-sm btn-outline-primary">Vollständige Tabelle</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>ID</th><th>Name</th><th>PID</th><th>Timeout (s)</th><th>Created</th></tr></thead>
            <tbody>
                <?php foreach ($processList as $p) {
                    echo '<tr><td>' . (int) $p['id'] . '</td><td>' . htmlspecialchars($p['name']) . '</td><td>' . (int) $p['pid'] . '</td><td>' . (int) $p['timeout'] . '</td><td>' . htmlspecialchars($p['created_at']) . '</td></tr>';
                } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<?php if (!empty($mediaObjectStats)) { ?>
<div class="card mb-4">
    <div class="card-header">Media Objects by Object Type &amp; Visibility</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Object Type</th><th>Visibility</th><th>Count</th></tr></thead>
            <tbody>
                <?php foreach ($mediaObjectStats as $s) {
                    echo '<tr><td>' . (int) $s['id_object_type'] . '</td><td>' . (int) $s['visibility'] . '</td><td>' . (int) $s['count'] . '</td></tr>';
                } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Last 10 Errors</span>
        <a href="<?php echo htmlspecialchars($baseUrl); ?>page=logs&type=ERROR" class="btn btn-sm btn-outline-primary">View all logs</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Text</th></tr></thead>
            <tbody>
                <?php
                if (empty($lastErrors)) {
                    echo '<tr><td colspan="4" class="text-muted">No errors</td></tr>';
                } else {
                    foreach ($lastErrors as $e) {
                        echo '<tr><td>' . htmlspecialchars($e['date']) . '</td><td>' . htmlspecialchars($e['type']) . '</td><td>' . htmlspecialchars($e['category']) . '</td><td>' . htmlspecialchars(mb_substr($e['text'], 0, 120)) . '</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

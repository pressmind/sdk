<?php
/** Sidebar navigation. Uses $baseUrl, $currentPage, $currentAction from layout. */
$baseUrl = $baseUrl ?? '?';
$currentPage = $currentPage ?? '';
?>
<ul class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=dashboard">Dashboard</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'config' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=config">Config</a>
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=config&action=raw">Raw-Ansicht</a></li>
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=config&action=diff">Diff (Dev vs Prod)</a></li>
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=dashboard&action=image_formats">Image Formats</a></li>
        </ul>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'imagecache' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=imagecache">Image Cache</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'commands' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=commands">Commands</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'logs' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=logs">Logs</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'data' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=data">Daten</a>
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=data">Tabellen</a></li>
        </ul>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'search' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=search">Suche (MongoDB)</a>
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=collections">Collections &amp; Indexes</a></li>
        </ul>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?php echo $currentPage === 'validation' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($baseUrl); ?>page=validation">Validierung</a>
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link text-white-50 small" href="<?php echo htmlspecialchars($baseUrl); ?>page=validation&action=orphans">Touristic Orphans</a></li>
        </ul>
    </li>
</ul>

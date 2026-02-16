<?php
$title = $title ?? 'Collections';
$collections = $collections ?? [];
$error = $error ?? null;
$baseUrl = $baseUrl ?? '';
?>
<h1><?php echo htmlspecialchars($title); ?></h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=search">Back to Search</a></p>
<?php if ($error !== null) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php } else { ?>
<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Collection</th>
            <th>Documents</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($collections as $c) { ?>
        <tr>
            <td><code><?php echo htmlspecialchars($c['name']); ?></code></td>
            <td><?php echo $c['count'] !== null ? number_format($c['count']) : '—'; ?></td>
            <td>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=query&format=html&collection=<?php echo urlencode($c['name']); ?>">Documents</a>
                ·
                <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=indexes&collection=<?php echo urlencode($c['name']); ?>">Indexes</a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>
<?php if (empty($collections)) { ?>
<p class="text-muted">No collections found.</p>
<?php } ?>
<?php } ?>

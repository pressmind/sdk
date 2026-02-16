<?php
$title = $title ?? 'Indexes';
$collection = $collection ?? '';
$indexes = $indexes ?? [];
$error = $error ?? null;
$baseUrl = $baseUrl ?? '';
?>
<h1><?php echo htmlspecialchars($title); ?></h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=collections">Collections</a></p>
<?php if ($error !== null) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php } else { ?>
<?php if ($collection !== '') { ?>
<p>Collection: <code><?php echo htmlspecialchars($collection); ?></code></p>
<?php } ?>
<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Index name</th>
            <th>Key</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($indexes as $idx) { ?>
        <tr>
            <td><code><?php echo htmlspecialchars($idx['name']); ?></code></td>
            <td><div class="json-view small" style="max-height: 200px;" data-no-toolbar data-json="<?php echo htmlspecialchars(json_encode($idx['key'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"></div></td>
        </tr>
        <?php } ?>
    </tbody>
</table>
<?php if (empty($indexes)) { ?>
<p class="text-muted">No indexes or collection not selected. Use <code>?collection=NAME</code>.</p>
<?php } ?>
<?php } ?>

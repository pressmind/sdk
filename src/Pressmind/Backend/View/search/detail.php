<?php
$title = $title ?? 'Document';
$collection = $collection ?? '';
$document = $document ?? null;
$error = $error ?? null;
$baseUrl = $baseUrl ?? '';
$id = $id ?? '';

$currentId = $id;
if ($document !== null) {
    $raw = $document['_id'] ?? null;
    if (is_array($raw) && isset($raw['$oid'])) {
        $currentId = $raw['$oid'];
    } elseif ($raw !== null) {
        $currentId = (string) $raw;
    }
}
?>
<h1 class="h3 mb-4"><?php echo htmlspecialchars($title); ?></h1>
<p>
    <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=collections">Collections</a>
    · <a href="<?php echo htmlspecialchars($baseUrl); ?>page=search&action=query&format=html&collection=<?php echo urlencode($collection); ?>">Documents</a>
</p>
<?php if ($collection !== '') { ?>
<form method="get" action="" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="search">
    <input type="hidden" name="action" value="detail">
    <input type="hidden" name="collection" value="<?php echo htmlspecialchars($collection); ?>">
    <div class="col-auto">
        <label for="detail-id" class="form-label small mb-0">id_media_object or _id</label>
        <input type="text" name="id" id="detail-id" class="form-control form-control-sm font-monospace" value="<?php echo htmlspecialchars((string) $currentId); ?>" required>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-success btn-sm">Show</button>
    </div>
</form>
<?php } ?>
<?php if ($error !== null) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php } elseif ($document === null) { ?>
<p class="text-muted">Document not found.</p>
<?php } else { ?>
<?php
$docJson = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<p class="small text-muted mb-2">Collection: <code><?php echo htmlspecialchars($collection); ?></code> · _id: <strong><?php echo htmlspecialchars($currentId); ?></strong></p>
<div class="json-view" data-json="<?php echo htmlspecialchars($docJson, ENT_QUOTES, 'UTF-8'); ?>" data-depth="2" data-theme="dark" style="max-height:80vh"></div>
<?php } ?>

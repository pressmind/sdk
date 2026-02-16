<?php
$section = $section ?? '';
$sectionLabel = $sectionLabel ?? '';
$data = $data ?? [];
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4"><?php echo htmlspecialchars($sectionLabel); ?></h1>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=config">&larr; Back to Config</a></p>
<?php
$sectionJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<div class="json-view" data-json="<?php echo htmlspecialchars($sectionJson, ENT_QUOTES, 'UTF-8'); ?>" data-depth="2"></div>

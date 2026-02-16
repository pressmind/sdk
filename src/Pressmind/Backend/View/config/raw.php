<?php
$config = $config ?? [];
$configJson = $configJson ?? '{}';
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
$baseUrl = ($baseUrl !== '' ? $baseUrl . '?' : '?');
?>
<h1 class="h3 mb-4">Config Raw</h1>
<p class="text-muted">Current environment config (read-only). Writing requires config_adapter in Registry.</p>
<p><a href="<?php echo htmlspecialchars($baseUrl); ?>page=config">&larr; Back to Config</a></p>
<?php
$rawJson = is_string($configJson) ? $configJson : json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<div id="config-raw-json-view" class="json-view mb-2" data-json="<?php echo htmlspecialchars($rawJson, ENT_QUOTES, 'UTF-8'); ?>" data-depth="1"></div>

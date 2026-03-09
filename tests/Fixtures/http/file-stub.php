<?php
header('Content-Type: application/octet-stream');
$size = max(1, (int)($_GET['size'] ?? 100));
echo random_bytes($size);

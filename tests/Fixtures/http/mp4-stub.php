<?php
if (($_GET['status'] ?? '') === '404') {
    http_response_code(404);
    echo 'not found';
    return;
}
header('Content-Type: video/mp4');
readfile(__DIR__ . '/../video/motion-clip.mp4');

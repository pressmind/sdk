<?php
/**
 * Bootstrap 5 layout with sidebar. Expects $contentView, $contentVars, $title, $currentPage, $currentAction.
 */
$contentVars = $contentVars ?? [];
$contentView = $contentView ?? '';
$title = $title ?? 'Backend';
$currentPage = $currentPage ?? 'dashboard';
$currentAction = $currentAction ?? 'index';
$baseUrl = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
if ($baseUrl === '') {
    $baseUrl = '?';
} else {
    $baseUrl .= '?';
}
extract($contentVars, EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?> – pressmind SDK Backend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark flex-shrink-0 align-items-start" style="width: 240px; min-height: 100vh;">
        <div class="navbar-collapse flex-column align-items-stretch w-100 p-3 justify-content-start">
            <a class="navbar-brand mb-3" href="<?php echo htmlspecialchars($baseUrl); ?>page=dashboard">pressmind SDK Backend</a>
            <?php
            $navPartial = __DIR__ . '/partials/nav.php';
            if (is_file($navPartial)) {
                include $navPartial;
            }
            ?>
        </div>
    </nav>
    <main class="flex-grow-1 p-4 overflow-auto">
        <?php
        if ($contentView !== '') {
            $contentPath = __DIR__ . '/' . ltrim($contentView, '/');
            if (is_file($contentPath)) {
                include $contentPath;
            }
        }
        ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    $jsonViewPartial = __DIR__ . '/partials/json-view.php';
    if (is_file($jsonViewPartial)) {
        include $jsonViewPartial;
    }
    ?>
</body>
</html>

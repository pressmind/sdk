<?php
/**
 * Pagination partial.
 * Vars: $pageNum (current 1-based), $totalPages, $baseUrl (query base e.g. "?page=logs&")
 */
$pageNum = (int) ($pageNum ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$baseUrl = $baseUrl ?? '?';
if ($totalPages <= 1) {
    return;
}
?>
<nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination">
        <li class="page-item <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum <= 1 ? '#' : htmlspecialchars($baseUrl . 'page_num=' . ($pageNum - 1)); ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++) {
            if ($i > 1 && $i < $totalPages && ($i < $pageNum - 2 || $i > $pageNum + 2)) {
                if ($i === $pageNum - 3 || $i === $pageNum + 3) {
                    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                continue;
            }
            ?>
            <li class="page-item <?php echo $i === $pageNum ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars($baseUrl . 'page_num=' . $i); ?>"><?php echo $i; ?></a>
            </li>
        <?php } ?>
        <li class="page-item <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageNum >= $totalPages ? '#' : htmlspecialchars($baseUrl . 'page_num=' . ($pageNum + 1)); ?>">Next</a>
        </li>
    </ul>
</nav>

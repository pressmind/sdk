<?php
/**
 * Generic sortable table partial.
 * Vars: $headers = [['key' => 'id', 'label' => 'ID', 'sortable' => true]], $rows = [['id' => 1, ...]], $sortKey, $sortOrder, $baseUrl
 */
$headers = $headers ?? [];
$rows = $rows ?? [];
$sortKey = $sortKey ?? null;
$sortOrder = $sortOrder ?? 'asc';
$baseUrl = $baseUrl ?? '?';
?>
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <?php foreach ($headers as $h) {
                    $label = $h['label'] ?? $h['key'] ?? '';
                    $key = $h['key'] ?? '';
                    $sortable = $h['sortable'] ?? true;
                    if ($sortable && $key !== '') {
                        $nextOrder = ($sortKey === $key && $sortOrder === 'asc') ? 'desc' : 'asc';
                        $url = $baseUrl . 'page=' . ($currentPage ?? 'data') . '&sort=' . $key . '&order=' . $nextOrder;
                        echo '<th scope="col"><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label);
                        if ($sortKey === $key) {
                            echo ' ' . ($sortOrder === 'asc' ? '↑' : '↓');
                        }
                        echo '</a></th>';
                    } else {
                        echo '<th scope="col">' . htmlspecialchars($label) . '</th>';
                    }
                } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) {
                echo '<tr>';
                foreach ($headers as $h) {
                    $key = $h['key'] ?? '';
                    $value = $row[$key] ?? '';
                    if (is_bool($value)) {
                        $value = $value ? 'Yes' : 'No';
                    }
                    echo '<td>' . htmlspecialchars((string) $value) . '</td>';
                }
                echo '</tr>';
            } ?>
        </tbody>
    </table>
</div>

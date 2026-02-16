<?php
/**
 * Reusable filter bar. Vars: $filters = [['name' => 'type', 'label' => 'Type', 'options' => [...], 'selected' => '']], $actionUrl, $submitLabel
 */
$filters = $filters ?? [];
$actionUrl = $actionUrl ?? ($_SERVER['REQUEST_URI'] ?? '?');
$submitLabel = $submitLabel ?? 'Filter';
?>
<form method="get" action="<?php echo htmlspecialchars($actionUrl); ?>" class="row g-2 align-items-end mb-3">
    <?php foreach ($filters as $f) {
        $name = $f['name'] ?? '';
        $label = $f['label'] ?? $name;
        $options = $f['options'] ?? [];
        $selected = $f['selected'] ?? '';
        $type = $f['type'] ?? 'select';
        ?>
        <div class="col-auto">
            <label for="filter-<?php echo htmlspecialchars($name); ?>" class="form-label small mb-0"><?php echo htmlspecialchars($label); ?></label>
            <?php if ($type === 'text') { ?>
                <input type="text" class="form-control form-control-sm" id="filter-<?php echo htmlspecialchars($name); ?>" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars((string) $selected); ?>">
            <?php } else { ?>
                <select class="form-select form-select-sm" id="filter-<?php echo htmlspecialchars($name); ?>" name="<?php echo htmlspecialchars($name); ?>">
                    <?php foreach ($options as $optValue => $optLabel) {
                        echo '<option value="' . htmlspecialchars((string) $optValue) . '"' . ($selected === (string) $optValue ? ' selected' : '') . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                    } ?>
                </select>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm"><?php echo htmlspecialchars($submitLabel); ?></button>
    </div>
</form>

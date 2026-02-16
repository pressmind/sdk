<?php
/**
 * Stat card for dashboard/validation. Vars: $label, $value, $subtitle (optional)
 */
$label = $label ?? '';
$value = $value ?? '0';
$subtitle = $subtitle ?? '';
?>
<div class="card">
    <div class="card-body">
        <h6 class="card-subtitle mb-1 text-muted"><?php echo htmlspecialchars($label); ?></h6>
        <p class="card-title h2 mb-0"><?php echo htmlspecialchars((string) $value); ?></p>
        <?php if ($subtitle !== '') { ?>
            <p class="card-text small text-muted mb-0"><?php echo htmlspecialchars($subtitle); ?></p>
        <?php } ?>
    </div>
</div>

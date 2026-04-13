<?php if (isset($pagination) && $pagination->totalPages > 1): ?>
<div class="pagination" style="display: flex; align-items: center; justify-content: center; gap: 4px; margin: 20px 0;">
    <?php if ($pagination->hasPrev()): ?>
        <a href="<?= htmlspecialchars($pagination->url($pagination->page - 1)) ?>" class="btn btn-sm">&laquo;</a>
    <?php endif; ?>

    <?php foreach ($pagination->visiblePages() as $p): ?>
        <?php if ($p === '...'): ?>
            <span style="padding: 6px 8px; color: #999;">...</span>
        <?php elseif ($p === $pagination->page): ?>
            <span class="btn btn-sm btn-primary" style="pointer-events:none;"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= htmlspecialchars($pagination->url($p)) ?>" class="btn btn-sm"><?= $p ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($pagination->hasNext()): ?>
        <a href="<?= htmlspecialchars($pagination->url($pagination->page + 1)) ?>" class="btn btn-sm">&raquo;</a>
    <?php endif; ?>

    <span style="margin-left: 12px; color: #666; font-size: 13px;">
        (<?= $pagination->total ?> <?= $pagination->total === 1 ? 'rekord' : 'rekordow' ?>)
    </span>
</div>
<?php endif; ?>

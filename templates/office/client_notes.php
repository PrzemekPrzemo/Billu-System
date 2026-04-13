<h1><?= $lang('internal_notes') ?>: <?= htmlspecialchars($client['company_name']) ?></h1>

<p><a href="/office/clients" class="btn btn-sm">&larr; <?= $lang('back_to_clients') ?></a></p>

<!-- Add/Edit note form -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><?= $lang('add_note') ?></div>
    <div class="card-body">
        <form method="POST" action="/office/clients/<?= $client['id'] ?>/notes">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <textarea name="note" rows="4" maxlength="5000" class="form-control" style="width:100%; margin-bottom:12px;" placeholder="<?= $lang('note_placeholder') ?>"><?= htmlspecialchars($notes[0]['note'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        </form>
    </div>
</div>

<?php if (!empty($notes)): ?>
<div class="card">
    <div class="card-header"><?= $lang('current_note') ?></div>
    <div class="card-body">
        <?php foreach ($notes as $n): ?>
        <div style="padding:12px; border-bottom:1px solid var(--gray-100); display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
            <div style="flex:1;">
                <div style="white-space:pre-wrap;"><?= htmlspecialchars($n['note']) ?></div>
                <div style="margin-top:6px; font-size:12px; color:var(--gray-500);">
                    <?= htmlspecialchars($n['created_by'] ?? '') ?> &middot; <?= date('Y-m-d H:i', strtotime($n['updated_at'])) ?>
                </div>
            </div>
            <div style="display:flex; gap:4px;">
                <form method="POST" action="/office/clients/<?= $client['id'] ?>/notes/toggle-pin" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-xs <?= $n['is_pinned'] ? 'btn-warning' : '' ?>" title="<?= $n['is_pinned'] ? $lang('unpin') : $lang('pin') ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= $n['is_pinned'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

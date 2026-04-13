<h1><?= $lang('cost_centers_management') ?> - <?= htmlspecialchars($client['company_name']) ?></h1>

<form method="POST" action="/admin/clients/<?= $client['id'] ?>/cost-centers" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="has_cost_centers" id="has-cost-centers"
                   <?= ($client['has_cost_centers'] ?? 0) ? 'checked' : '' ?>
                   onchange="toggleCostCenters()">
            <?= $lang('enable_cost_centers') ?>
        </label>
        <small class="form-hint"><?= $lang('enable_cost_centers_hint') ?></small>
    </div>

    <div id="cost-centers-list" style="<?= ($client['has_cost_centers'] ?? 0) ? '' : 'display:none;' ?>">
        <div id="cc-entries">
            <?php
            $ccList = $costCenters ?? [];
            if (empty($ccList)) $ccList = [['name' => '']]; // At least one empty
            foreach ($ccList as $i => $cc):
            ?>
            <div class="form-group cc-entry" style="display:flex;gap:8px;align-items:center;">
                <span style="min-width:20px;"><?= $i + 1 ?>.</span>
                <input type="text" name="cost_center_names[]" class="form-input"
                       value="<?= htmlspecialchars($cc['name'] ?? '') ?>"
                       placeholder="<?= $lang('cost_center_placeholder') ?>" maxlength="255">
                <button type="button" class="btn btn-xs btn-danger" onclick="removeCostCenter(this)">&times;</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addCostCenter()" id="add-cc-btn">
            + <?= $lang('add_cost_center') ?>
        </button>
        <small class="form-hint"><?= $lang('max_cost_centers') ?></small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/admin/clients" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
function toggleCostCenters() {
    var list = document.getElementById('cost-centers-list');
    list.style.display = document.getElementById('has-cost-centers').checked ? '' : 'none';
}
function addCostCenter() {
    var entries = document.querySelectorAll('.cc-entry');
    if (entries.length >= 10) { alert('<?= $lang('max_cost_centers') ?>'); return; }
    var num = entries.length + 1;
    var div = document.createElement('div');
    div.className = 'form-group cc-entry';
    div.style.cssText = 'display:flex;gap:8px;align-items:center;';
    div.innerHTML = '<span style="min-width:20px;">' + num + '.</span>' +
        '<input type="text" name="cost_center_names[]" class="form-input" placeholder="<?= $lang('cost_center_placeholder') ?>" maxlength="255">' +
        '<button type="button" class="btn btn-xs btn-danger" onclick="removeCostCenter(this)">&times;</button>';
    document.getElementById('cc-entries').appendChild(div);
    updateAddBtn();
}
function removeCostCenter(btn) {
    btn.closest('.cc-entry').remove();
    renumberCostCenters();
    updateAddBtn();
}
function renumberCostCenters() {
    document.querySelectorAll('.cc-entry').forEach(function(el, i) {
        el.querySelector('span').textContent = (i + 1) + '.';
    });
}
function updateAddBtn() {
    var btn = document.getElementById('add-cc-btn');
    if (btn) btn.style.display = document.querySelectorAll('.cc-entry').length >= 10 ? 'none' : '';
}
</script>

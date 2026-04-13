<h1><?= $lang('services_catalog') ?></h1>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="section">
    <div class="form-card" style="padding:20px;">
        <?php if (empty($services)): ?>
            <p style="color:var(--gray-500); margin-bottom:16px;"><?= $lang('no_services') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th><?= $lang('service_name') ?></th>
                            <th><?= $lang('unit') ?></th>
                            <th><?= $lang('default_price') ?></th>
                            <th><?= $lang('vat_rate') ?></th>
                            <th><?= $lang('pkwiu') ?></th>
                            <th><?= $lang('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $svc): ?>
                        <tr>
                            <td><?= htmlspecialchars($svc['name']) ?></td>
                            <td><?= htmlspecialchars($svc['unit'] ?? 'szt.') ?></td>
                            <td><?= $svc['default_price'] ? number_format((float)$svc['default_price'], 2, ',', ' ') : '-' ?></td>
                            <td><?= htmlspecialchars($svc['vat_rate'] ?? '23') ?>%</td>
                            <td><?= htmlspecialchars($svc['pkwiu'] ?? '-') ?></td>
                            <td>
                                <form method="POST" action="/client/services/<?= $svc['id'] ?>/delete" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?= $lang('delete') ?>?')"><?= $lang('delete') ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 style="margin-bottom:12px;"><?= $lang('add_service') ?></h3>
        <form method="POST" action="/client/services/create">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label"><?= $lang('service_name') ?></label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('unit') ?></label>
                    <select name="unit" class="form-input">
                        <option value="szt.">szt.</option>
                        <option value="godz.">godz.</option>
                        <option value="usł.">usł.</option>
                        <option value="m2">m2</option>
                        <option value="kg">kg</option>
                        <option value="km">km</option>
                        <option value="kpl.">kpl.</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('default_price') ?></label>
                    <input type="number" name="default_price" class="form-input" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('vat_rate') ?></label>
                    <select name="vat_rate" class="form-input">
                        <option value="23">23%</option>
                        <option value="8">8%</option>
                        <option value="5">5%</option>
                        <option value="0">0%</option>
                        <option value="zw">zw.</option>
                        <option value="np">np.</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('pkwiu') ?></label>
                    <input type="text" name="pkwiu" class="form-input" maxlength="20">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $lang('add_service') ?></button>
        </form>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php
$archiveReasons = [
    'end_of_contract' => 'Wygaśnięcie umowy o pracę',
    'resignation'     => 'Wypowiedzenie przez pracownika',
    'dismissal'       => 'Wypowiedzenie przez pracodawcę',
    'other'           => 'Porozumienie stron / inne',
];

// Find the current (is_current=1) contract for default selection
$currentContract = null;
foreach ($contracts as $c) {
    if ($c['is_current']) { $currentContract = $c; break; }
}
if (!$currentContract && !empty($contracts)) {
    $currentContract = $contracts[0];
}
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>"><?= htmlspecialchars($employee['full_name']) ?></a> &rsaquo;
            <?= $lang('hr_archive_employee') ?>
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;color:#c0392b;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <?= $lang('hr_archive_employee') ?>: <?= htmlspecialchars($employee['full_name']) ?>
        </h1>
    </div>
    <div>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</div>

<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<!-- Warning banner -->
<div class="alert" style="background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:20px;padding:14px 16px;">
    <strong>Uwaga — operacja nieodwracalna</strong><br/>
    Archiwizacja pracownika dezaktywuje jego konto i oznacza zakończenie zatrudnienia.
    Pracownik nie będzie pojawiał się na listach płac ani w ewidencji czasu pracy.
    Historyczne dane (listy płac, urlopy) pozostają w systemie.
</div>

<div class="card" style="max-width:680px;">
    <div class="card-header">
        <h3><?= $lang('hr_archive_details') ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/archive-confirm"
              onsubmit="return confirm('Potwierdzasz archiwizację pracownika <?= htmlspecialchars(addslashes($employee['full_name'])) ?>?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <!-- Reason -->
            <div class="form-group">
                <label for="archive_reason" class="form-label required"><?= $lang('hr_archive_reason') ?></label>
                <select id="archive_reason" name="archive_reason" class="form-control" required>
                    <?php foreach ($archiveReasons as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- End date -->
            <div class="form-group">
                <label for="end_date" class="form-label required"><?= $lang('hr_employment_end') ?></label>
                <input type="date" id="end_date" name="end_date" class="form-control"
                       value="<?= date('Y-m-d') ?>" required>
                <small class="form-hint"><?= $lang('hr_archive_end_date_hint') ?></small>
            </div>

            <!-- Contract to terminate -->
            <?php if (!empty($contracts)): ?>
            <div class="form-group">
                <label for="contract_id" class="form-label"><?= $lang('hr_archive_contract') ?></label>
                <select id="contract_id" name="contract_id" class="form-control">
                    <option value="0">— nie terminuj umowy automatycznie —</option>
                    <?php foreach ($contracts as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($currentContract && (int)$c['id'] === (int)$currentContract['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($c['contract_type'])) ?>
                        (<?= htmlspecialchars($c['start_date']) ?><?= $c['end_date'] ? ' – ' . htmlspecialchars($c['end_date']) : ', bezterminowa' ?>)
                        <?= $c['is_current'] ? '✓ aktualna' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint"><?= $lang('hr_archive_contract_hint') ?></small>
            </div>
            <?php endif; ?>

            <!-- Generate świadectwo checkbox -->
            <div class="form-group">
                <label class="form-checkbox-label" style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="generate_swiadectwo" value="1"
                           <?= $currentContract ? 'checked' : '' ?>
                           id="gen_swiadectwo">
                    <span>
                        <strong><?= $lang('hr_archive_gen_swiadectwo') ?></strong><br/>
                        <small style="color:var(--text-muted);"><?= $lang('hr_archive_gen_swiadectwo_hint') ?></small>
                    </span>
                </label>
                <?php if (!$currentContract): ?>
                <p style="color:#c0392b;font-size:13px;margin:6px 0 0;">
                    <?= $lang('hr_archive_no_contract_warning') ?>
                </p>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:12px;margin-top:24px;">
                <button type="submit" class="btn btn-danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?= $lang('hr_archive_confirm') ?>
                </button>
                <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>" class="btn btn-secondary">
                    <?= $lang('cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Employee summary card -->
<div class="card" style="max-width:680px;margin-top:16px;">
    <div class="card-header"><h3><?= $lang('hr_employee_summary') ?></h3></div>
    <div class="card-body">
        <table class="detail-table">
            <tr><th><?= $lang('name') ?></th><td><?= htmlspecialchars($employee['full_name']) ?></td></tr>
            <tr><th>PESEL</th><td><?= \App\Models\HrEmployee::maskPesel($employee['pesel']) ?></td></tr>
            <?php if ($employee['employment_start']): ?>
            <tr><th><?= $lang('hr_employment_start') ?></th><td><?= htmlspecialchars($employee['employment_start']) ?></td></tr>
            <?php endif; ?>
            <?php if ($currentContract): ?>
            <tr><th><?= $lang('hr_contract_type') ?></th>
                <td><?= htmlspecialchars(\App\Models\HrContract::getContractTypeLabel($currentContract['contract_type'])) ?></td></tr>
            <tr><th><?= $lang('hr_position') ?></th><td><?= htmlspecialchars($currentContract['position'] ?? '—') ?></td></tr>
            <tr><th><?= $lang('hr_base_salary') ?></th>
                <td><?= number_format((float)$currentContract['base_salary'], 2, ',', ' ') ?> PLN</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></a> &rsaquo;
            <?= $lang('hr_add_contract') ?>
        </div>
        <h1><?= $lang('hr_add_contract') ?></h1>
    </div>
</div>

<?php if ($flash_error): ?><div class="alert alert-error"><?= $flash_error ?></div><?php endif; ?>

<form method="POST" action="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>/contracts/create">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_contract_type_and_dates') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_contract_type') ?> <span class="text-danger">*</span></label>
                    <select name="contract_type" class="form-control" id="contract_type_sel" onchange="updateZusDefaults(this.value)">
                        <option value="uop">Umowa o pracę (UoP)</option>
                        <option value="uz">Umowa zlecenie (UZ)</option>
                        <option value="uod">Umowa o dzieło (UoD)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_contract_number') ?></label>
                    <input type="text" name="contract_number" class="form-control" placeholder="np. UoP/2025/001">
                </div>
                <div class="form-group">
                    <label class="form-label">Data rozpoczęcia <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Data zakończenia <span class="text-muted">(puste = bezterminowo)</span></label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_position') ?></label>
                    <input type="text" name="position" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_department') ?></label>
                    <input type="text" name="department" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3><?= $lang('hr_salary') ?></h3></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_salary_type') ?></label>
                    <select name="salary_type" class="form-control" onchange="toggleHourlyRate(this.value)">
                        <option value="monthly">Miesięczne</option>
                        <option value="hourly">Godzinowe</option>
                        <option value="task">Ryczałt (za zadanie)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_base_salary') ?> brutto (PLN)</label>
                    <input type="number" name="base_salary" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group" id="hourly_rate_group" style="display:none;">
                    <label class="form-label">Stawka godzinowa (PLN/godz.)</label>
                    <input type="number" name="hourly_rate" class="form-control" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= $lang('hr_work_time_fraction') ?></label>
                    <select name="work_time_fraction" class="form-control">
                        <option value="1.00">Pełny etat (1/1)</option>
                        <option value="0.75">3/4 etatu</option>
                        <option value="0.50">1/2 etatu</option>
                        <option value="0.25">1/4 etatu</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;" id="zus_section">
        <div class="card-header"><h3>Składki ZUS</h3></div>
        <div class="card-body">
            <div id="zus_note_uod" class="alert alert-info" style="display:none;">
                Umowa o dzieło — brak obowiązku odprowadzania składek ZUS (z wyjątkiem umów z własnym pracodawcą).
            </div>
            <div id="zus_note_uz" class="alert alert-info" style="display:none;">
                Umowa zlecenie — ZUS obowiązkowy (chyba że zleceniobiorca ma UoP z wynagrodzeniem ≥ minimalnego).
            </div>
            <div id="zus_checkboxes">
                <?php
                $zusFields = [
                    'zus_emerytalne_employee' => 'Emerytalne — pracownik (9.76%)',
                    'zus_emerytalne_employer' => 'Emerytalne — pracodawca (9.76%)',
                    'zus_rentowe_employee'    => 'Rentowe — pracownik (1.50%)',
                    'zus_rentowe_employer'    => 'Rentowe — pracodawca (6.50%)',
                    'zus_chorobowe'           => 'Chorobowe — pracownik (2.45%)',
                    'zus_wypadkowe'           => 'Wypadkowe — pracodawca (1.67% domyślnie)',
                    'zus_fp'                  => 'Fundusz Pracy (2.45%)',
                    'zus_fgsp'                => 'FGŚP (0.10%)',
                    'zus_fep'                 => 'FEP — Fundusz Emerytur Pomostowych (opcjonalne)',
                ];
                foreach ($zusFields as $name => $label):
                ?>
                <label class="form-label-check" style="display:block;margin-bottom:6px;">
                    <input type="hidden" name="<?= $name ?>" value="0">
                    <input type="checkbox" name="<?= $name ?>" value="1"
                           <?= $name !== 'zus_fep' ? 'checked' : '' ?>>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
                <label class="form-label-check" style="display:block;margin-top:12px;">
                    <input type="hidden" name="has_other_employment" value="0">
                    <input type="checkbox" name="has_other_employment" value="1">
                    Zleceniobiorca ma inne zatrudnienie (UoP z wynagrodzeniem ≥ minimalnego) — zwolnienie z ZUS
                </label>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/hr/<?= $clientId ?>/employees/<?= $employee['id'] ?>" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
function updateZusDefaults(type) {
    document.getElementById('zus_note_uod').style.display = type === 'uod' ? 'block' : 'none';
    document.getElementById('zus_note_uz').style.display  = type === 'uz'  ? 'block' : 'none';
}
function toggleHourlyRate(val) {
    document.getElementById('hourly_rate_group').style.display = val === 'hourly' ? 'block' : 'none';
}
</script>
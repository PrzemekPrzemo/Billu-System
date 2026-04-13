<div class="section-header">
    <h1><?= $contract ? 'Edytuj umowe' : 'Nowa umowa' ?> - <?= htmlspecialchars($client['company_name']) ?></h1>
    <a href="/office/hr/<?= $client['id'] ?>/contracts" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php $flash = \App\Core\Session::getFlash('error'); ?>
<?php if ($flash): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $contract ? '/office/hr/' . $client['id'] . '/contracts/' . $contract['id'] . '/update' : '/office/hr/' . $client['id'] . '/contracts/create' ?>" class="form-card" id="contractForm">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <h3 style="margin-bottom:16px;">Dane podstawowe</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Pracownik *</label>
            <select name="employee_id" class="form-input" required>
                <option value="">-- Wybierz pracownika --</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= ($contract['employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars($emp['pesel'] ?? '') ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Typ umowy *</label>
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <label class="checkbox-label">
                <input type="radio" name="contract_type" value="umowa_o_prace" required
                    <?= ($contract['contract_type'] ?? 'umowa_o_prace') === 'umowa_o_prace' ? 'checked' : '' ?>
                    onchange="toggleContractFields()">
                Umowa o prace
            </label>
            <label class="checkbox-label">
                <input type="radio" name="contract_type" value="umowa_zlecenie"
                    <?= ($contract['contract_type'] ?? '') === 'umowa_zlecenie' ? 'checked' : '' ?>
                    onchange="toggleContractFields()">
                Umowa zlecenie
            </label>
            <label class="checkbox-label">
                <input type="radio" name="contract_type" value="umowa_o_dzielo"
                    <?= ($contract['contract_type'] ?? '') === 'umowa_o_dzielo' ? 'checked' : '' ?>
                    onchange="toggleContractFields()">
                Umowa o dzielo
            </label>
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Wynagrodzenie</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Wynagrodzenie brutto (PLN) *</label>
            <input type="number" name="gross_salary" class="form-input" required step="0.01" min="0"
                   value="<?= htmlspecialchars($contract['gross_salary'] ?? '') ?>"
                   placeholder="4666.00">
        </div>
        <div class="form-group">
            <label class="form-label">Typ wynagrodzenia</label>
            <select name="salary_type" class="form-input">
                <option value="monthly" <?= ($contract['salary_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Miesieczne</option>
                <option value="hourly" <?= ($contract['salary_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>Godzinowe</option>
                <option value="task" <?= ($contract['salary_type'] ?? '') === 'task' ? 'selected' : '' ?>>Akordowe</option>
            </select>
        </div>
    </div>

    <!-- Fields: Umowa o prace -->
    <div id="fields-umowa_o_prace" class="contract-type-fields">
        <h3 style="margin-top:24px; margin-bottom:16px;">Szczegoly umowy o prace</h3>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Wymiar etatu</label>
                <select name="work_time_fraction" class="form-input">
                    <option value="1/1" <?= ($contract['work_time_fraction'] ?? '1/1') === '1/1' ? 'selected' : '' ?>>Pelny etat (1/1)</option>
                    <option value="1/2" <?= ($contract['work_time_fraction'] ?? '') === '1/2' ? 'selected' : '' ?>>1/2 etatu</option>
                    <option value="3/4" <?= ($contract['work_time_fraction'] ?? '') === '3/4' ? 'selected' : '' ?>>3/4 etatu</option>
                    <option value="1/4" <?= ($contract['work_time_fraction'] ?? '') === '1/4' ? 'selected' : '' ?>>1/4 etatu</option>
                    <option value="1/3" <?= ($contract['work_time_fraction'] ?? '') === '1/3' ? 'selected' : '' ?>>1/3 etatu</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Stanowisko</label>
                <input type="text" name="position" class="form-input"
                       value="<?= htmlspecialchars($contract['position'] ?? '') ?>"
                       placeholder="np. Specjalista ds. marketingu">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Miejsce pracy</label>
                <input type="text" name="workplace" class="form-input"
                       value="<?= htmlspecialchars($contract['workplace'] ?? '') ?>"
                       placeholder="np. Warszawa, siedziba firmy">
            </div>
            <div class="form-group">
                <label class="form-label">Koszty uzyskania przychodu</label>
                <select name="tax_deductible_costs" class="form-input">
                    <option value="basic" <?= ($contract['tax_deductible_costs'] ?? 'basic') === 'basic' ? 'selected' : '' ?>>Podstawowe (250 PLN)</option>
                    <option value="elevated" <?= ($contract['tax_deductible_costs'] ?? '') === 'elevated' ? 'selected' : '' ?>>Podwyzszone (300 PLN)</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="uses_kwota_wolna" value="1"
                    <?= ($contract['uses_kwota_wolna'] ?? 1) ? 'checked' : '' ?>>
                Stosuj kwote wolna od podatku (PIT-2)
            </label>
        </div>
    </div>

    <!-- Fields: Umowa zlecenie -->
    <div id="fields-umowa_zlecenie" class="contract-type-fields" style="display:none;">
        <h3 style="margin-top:24px; margin-bottom:16px;">Skladki ZUS - Umowa zlecenie</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
            <label class="checkbox-label">
                <input type="checkbox" name="zus_emerytalna" value="1"
                    <?= ($contract['zus_emerytalna'] ?? 1) ? 'checked' : '' ?>>
                Emerytalna
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_rentowa" value="1"
                    <?= ($contract['zus_rentowa'] ?? 1) ? 'checked' : '' ?>>
                Rentowa
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_chorobowa" value="1"
                    <?= ($contract['zus_chorobowa'] ?? 0) ? 'checked' : '' ?>>
                Chorobowa (dobrowolna)
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_wypadkowa" value="1"
                    <?= ($contract['zus_wypadkowa'] ?? 1) ? 'checked' : '' ?>>
                Wypadkowa
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_zdrowotna" value="1"
                    <?= ($contract['zus_zdrowotna'] ?? 1) ? 'checked' : '' ?>>
                Zdrowotna
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_fp" value="1"
                    <?= ($contract['zus_fp'] ?? 1) ? 'checked' : '' ?>>
                Fundusz Pracy
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="zus_fgsp" value="1"
                    <?= ($contract['zus_fgsp'] ?? 1) ? 'checked' : '' ?>>
                FGSP
            </label>
        </div>
    </div>

    <!-- Fields: Umowa o dzielo -->
    <div id="fields-umowa_o_dzielo" class="contract-type-fields" style="display:none;">
        <h3 style="margin-top:24px; margin-bottom:16px;">Koszty uzyskania przychodu - Umowa o dzielo</h3>
        <div class="form-group">
            <label class="form-label">Stawka KUP</label>
            <div style="display:flex; gap:16px;">
                <label class="checkbox-label">
                    <input type="radio" name="dzielo_kup_rate" value="20"
                        <?= ($contract['dzielo_kup_rate'] ?? '20') == '20' ? 'checked' : '' ?>>
                    20% (standardowe)
                </label>
                <label class="checkbox-label">
                    <input type="radio" name="dzielo_kup_rate" value="50"
                        <?= ($contract['dzielo_kup_rate'] ?? '') == '50' ? 'checked' : '' ?>>
                    50% (prawa autorskie)
                </label>
            </div>
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Okres obowiazywania</h3>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Data rozpoczecia *</label>
            <input type="date" name="start_date" class="form-input" required
                   value="<?= htmlspecialchars($contract['start_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Data zakonczenia</label>
            <input type="date" name="end_date" class="form-input"
                   value="<?= htmlspecialchars($contract['end_date'] ?? '') ?>">
            <small class="form-hint">Pozostaw puste dla umowy na czas nieokreslony</small>
        </div>
    </div>

    <h3 style="margin-top:24px; margin-bottom:16px;">Dodatkowe opcje</h3>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="pit_exempt" value="1"
                <?= ($contract['pit_exempt'] ?? 0) ? 'checked' : '' ?>>
            Zwolnienie z PIT (ulga dla mlodych do 26 r.z.)
        </label>
    </div>

    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" name="ppk_active" value="1" id="ppkActive"
                <?= ($contract['ppk_active'] ?? 0) ? 'checked' : '' ?>
                onchange="document.getElementById('ppkFields').style.display = this.checked ? 'flex' : 'none';">
            Uczestnik PPK
        </label>
    </div>

    <div class="form-row" id="ppkFields" style="<?= ($contract['ppk_active'] ?? 0) ? '' : 'display:none;' ?>">
        <div class="form-group">
            <label class="form-label">Skladka pracownika PPK (%)</label>
            <input type="number" name="ppk_employee_rate" class="form-input" step="0.01" min="0.5" max="4"
                   value="<?= htmlspecialchars($contract['ppk_employee_rate'] ?? '2.00') ?>"
                   placeholder="2.00">
        </div>
        <div class="form-group">
            <label class="form-label">Skladka pracodawcy PPK (%)</label>
            <input type="number" name="ppk_employer_rate" class="form-input" step="0.01" min="1.5" max="4"
                   value="<?= htmlspecialchars($contract['ppk_employer_rate'] ?? '1.50') ?>"
                   placeholder="1.50">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Status umowy</label>
            <select name="status" class="form-input">
                <option value="draft" <?= ($contract['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Szkic</option>
                <option value="active" <?= ($contract['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywna</option>
                <option value="terminated" <?= ($contract['status'] ?? '') === 'terminated' ? 'selected' : '' ?>>Rozwiazana</option>
                <option value="expired" <?= ($contract['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Wygasla</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Notatki</label>
        <textarea name="notes" class="form-input" rows="3" placeholder="Dodatkowe uwagi..."><?= htmlspecialchars($contract['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $lang('save') ?></button>
        <a href="/office/hr/<?= $client['id'] ?>/contracts" class="btn btn-secondary"><?= $lang('cancel') ?></a>
    </div>
</form>

<script>
function toggleContractFields() {
    var type = document.querySelector('input[name="contract_type"]:checked');
    if (!type) return;
    var sections = document.querySelectorAll('.contract-type-fields');
    for (var i = 0; i < sections.length; i++) {
        sections[i].style.display = 'none';
    }
    var target = document.getElementById('fields-' + type.value);
    if (target) {
        target.style.display = 'block';
    }
}
document.addEventListener('DOMContentLoaded', toggleContractFields);
</script>

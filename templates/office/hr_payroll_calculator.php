<?php $fmt = fn($v) => number_format((float)$v, 2, ',', ' '); ?>

<div class="section-header">
    <h1>Kalkulator wynagrodzen</h1>
    <a href="/office/hr" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<div class="form-card" style="padding:20px; margin-bottom:20px;">
    <h3 style="margin-bottom:16px;">Oblicz wynagrodzenie brutto &rarr; netto</h3>
    <form method="POST" action="/office/hr/calculator" id="calcForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="form-group">
            <label class="form-label">Typ umowy</label>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <label class="checkbox-label">
                    <input type="radio" name="contract_type" value="umowa_o_prace" required
                        <?= ($calcInput['contract_type'] ?? 'umowa_o_prace') === 'umowa_o_prace' ? 'checked' : '' ?>
                        onchange="toggleCalcFields()">
                    Umowa o prace
                </label>
                <label class="checkbox-label">
                    <input type="radio" name="contract_type" value="umowa_zlecenie"
                        <?= ($calcInput['contract_type'] ?? '') === 'umowa_zlecenie' ? 'checked' : '' ?>
                        onchange="toggleCalcFields()">
                    Umowa zlecenie
                </label>
                <label class="checkbox-label">
                    <input type="radio" name="contract_type" value="umowa_o_dzielo"
                        <?= ($calcInput['contract_type'] ?? '') === 'umowa_o_dzielo' ? 'checked' : '' ?>
                        onchange="toggleCalcFields()">
                    Umowa o dzielo
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Wynagrodzenie brutto (PLN) *</label>
                <input type="number" name="gross_salary" class="form-input" required step="0.01" min="0"
                       value="<?= htmlspecialchars($calcInput['gross_salary'] ?? '') ?>"
                       placeholder="np. 6000.00" style="font-size:18px; font-weight:600;">
            </div>
        </div>

        <!-- UoP options -->
        <div id="calc-uop" class="calc-type-fields">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Koszty uzyskania przychodu</label>
                    <select name="tax_deductible_costs" class="form-input">
                        <option value="basic" <?= ($calcInput['tax_deductible_costs'] ?? 'basic') === 'basic' ? 'selected' : '' ?>>Podstawowe (250 PLN)</option>
                        <option value="elevated" <?= ($calcInput['tax_deductible_costs'] ?? '') === 'elevated' ? 'selected' : '' ?>>Podwyzszone (300 PLN)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Wymiar etatu</label>
                    <select name="work_time_fraction" class="form-input">
                        <option value="1" <?= ($calcInput['work_time_fraction'] ?? '1') === '1' ? 'selected' : '' ?>>Pelny etat</option>
                        <option value="0.5" <?= ($calcInput['work_time_fraction'] ?? '') === '0.5' ? 'selected' : '' ?>>1/2 etatu</option>
                        <option value="0.75" <?= ($calcInput['work_time_fraction'] ?? '') === '0.75' ? 'selected' : '' ?>>3/4 etatu</option>
                        <option value="0.25" <?= ($calcInput['work_time_fraction'] ?? '') === '0.25' ? 'selected' : '' ?>>1/4 etatu</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <label class="checkbox-label">
                    <input type="checkbox" name="uses_kwota_wolna" value="1" <?= ($calcInput['uses_kwota_wolna'] ?? 1) ? 'checked' : '' ?>>
                    Kwota wolna (PIT-2)
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="ppk_active" value="1" <?= ($calcInput['ppk_active'] ?? 0) ? 'checked' : '' ?>>
                    PPK
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="pit_exempt" value="1" <?= ($calcInput['pit_exempt'] ?? 0) ? 'checked' : '' ?>>
                    Ulga dla mlodych (do 26 r.z.)
                </label>
            </div>
        </div>

        <!-- Zlecenie options -->
        <div id="calc-zlecenie" class="calc-type-fields" style="display:none;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:8px;">
                <label class="checkbox-label">
                    <input type="checkbox" name="calc_zus_emerytalna" value="1" <?= ($calcInput['calc_zus_emerytalna'] ?? 1) ? 'checked' : '' ?>>
                    Emerytalna
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="calc_zus_rentowa" value="1" <?= ($calcInput['calc_zus_rentowa'] ?? 1) ? 'checked' : '' ?>>
                    Rentowa
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="calc_zus_chorobowa" value="1" <?= ($calcInput['calc_zus_chorobowa'] ?? 0) ? 'checked' : '' ?>>
                    Chorobowa (dobrowolna)
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="calc_zus_zdrowotna" value="1" <?= ($calcInput['calc_zus_zdrowotna'] ?? 1) ? 'checked' : '' ?>>
                    Zdrowotna
                </label>
            </div>
        </div>

        <!-- Dzielo options -->
        <div id="calc-dzielo" class="calc-type-fields" style="display:none;">
            <div class="form-group">
                <label class="form-label">Stawka KUP</label>
                <div style="display:flex; gap:16px;">
                    <label class="checkbox-label">
                        <input type="radio" name="calc_dzielo_kup" value="20" <?= ($calcInput['calc_dzielo_kup'] ?? '20') == '20' ? 'checked' : '' ?>>
                        20% (standardowe)
                    </label>
                    <label class="checkbox-label">
                        <input type="radio" name="calc_dzielo_kup" value="50" <?= ($calcInput['calc_dzielo_kup'] ?? '') == '50' ? 'checked' : '' ?>>
                        50% (prawa autorskie)
                    </label>
                </div>
            </div>
        </div>

        <div style="margin-top:16px;">
            <button type="submit" class="btn btn-primary"><?= $lang('calculate') ?></button>
        </div>
    </form>
</div>

<?php if (!empty($calcResult)): ?>
<div class="form-card" style="padding:20px;">
    <h3 style="margin-bottom:16px;">Wynik kalkulacji</h3>

    <!-- Summary -->
    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="form-card" style="padding:16px; text-align:center; border-top:3px solid #2563eb;">
            <div style="font-size:13px; color:var(--gray-500); margin-bottom:6px;">Brutto</div>
            <div style="font-size:24px; font-weight:700; color:#2563eb;"><?= $fmt($calcResult['gross'] ?? 0) ?> PLN</div>
        </div>
        <div class="form-card" style="padding:16px; text-align:center; border-top:3px solid #16a34a;">
            <div style="font-size:13px; color:var(--gray-500); margin-bottom:6px;">Netto</div>
            <div style="font-size:24px; font-weight:700; color:#16a34a;"><?= $fmt($calcResult['net'] ?? 0) ?> PLN</div>
        </div>
        <div class="form-card" style="padding:16px; text-align:center; border-top:3px solid #d97706;">
            <div style="font-size:13px; color:var(--gray-500); margin-bottom:6px;">Koszt pracodawcy</div>
            <div style="font-size:24px; font-weight:700; color:#d97706;"><?= $fmt($calcResult['employer_cost'] ?? 0) ?> PLN</div>
        </div>
    </div>

    <!-- Breakdown table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Skladnik</th>
                    <th class="text-right">Kwota (PLN)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Wynagrodzenie brutto</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums; font-weight:600;"><?= $fmt($calcResult['gross'] ?? 0) ?></td>
                </tr>
                <?php if (isset($calcResult['zus_emerytalna'])): ?>
                <tr>
                    <td class="text-muted">Skladka emerytalna (pracownik)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['zus_emerytalna'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['zus_rentowa'])): ?>
                <tr>
                    <td class="text-muted">Skladka rentowa (pracownik)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['zus_rentowa'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['zus_chorobowa'])): ?>
                <tr>
                    <td class="text-muted">Skladka chorobowa</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['zus_chorobowa'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['zus_total_employee'])): ?>
                <tr style="font-weight:600;">
                    <td>Skladki ZUS pracownika razem</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['zus_total_employee'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['health_insurance'])): ?>
                <tr>
                    <td class="text-muted">Skladka zdrowotna</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['health_insurance'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['tax_deductible_costs'])): ?>
                <tr>
                    <td class="text-muted">Koszty uzyskania przychodu</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['tax_deductible_costs'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['pit_base'])): ?>
                <tr>
                    <td class="text-muted">Podstawa opodatkowania</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['pit_base'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['pit_advance'])): ?>
                <tr>
                    <td class="text-muted">Zaliczka na PIT</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['pit_advance'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($calcResult['ppk_employee']) && (float)($calcResult['ppk_employee'] ?? 0) > 0): ?>
                <tr>
                    <td class="text-muted">PPK (pracownik)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;">- <?= $fmt($calcResult['ppk_employee'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="font-weight:700; background:var(--gray-50);">
                    <td>Wynagrodzenie netto</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums; color:#16a34a;"><?= $fmt($calcResult['net'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if (isset($calcResult['employer_zus_emerytalna'])): ?>
    <h4 style="margin-top:20px; margin-bottom:12px;">Skladki pracodawcy</h4>
    <div class="table-responsive">
        <table class="table">
            <tbody>
                <tr>
                    <td class="text-muted">Emerytalna (pracodawca)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['employer_zus_emerytalna'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Rentowa (pracodawca)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['employer_zus_rentowa'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Wypadkowa</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['employer_zus_wypadkowa'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Fundusz Pracy</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['employer_fp'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">FGSP</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['employer_fgsp'] ?? 0) ?></td>
                </tr>
                <?php if (isset($calcResult['ppk_employer']) && (float)($calcResult['ppk_employer'] ?? 0) > 0): ?>
                <tr>
                    <td class="text-muted">PPK (pracodawca)</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums;"><?= $fmt($calcResult['ppk_employer'] ?? 0) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="font-weight:700; background:var(--gray-50);">
                    <td>Calkowity koszt pracodawcy</td>
                    <td class="text-right" style="font-variant-numeric:tabular-nums; color:#d97706;"><?= $fmt($calcResult['employer_cost'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleCalcFields() {
    var type = document.querySelector('#calcForm input[name="contract_type"]:checked');
    if (!type) return;
    var allFields = document.querySelectorAll('.calc-type-fields');
    for (var i = 0; i < allFields.length; i++) {
        allFields[i].style.display = 'none';
    }
    var map = {
        'umowa_o_prace': 'calc-uop',
        'umowa_zlecenie': 'calc-zlecenie',
        'umowa_o_dzielo': 'calc-dzielo'
    };
    var target = document.getElementById(map[type.value]);
    if (target) target.style.display = 'block';
}
document.addEventListener('DOMContentLoaded', toggleCalcFields);
</script>

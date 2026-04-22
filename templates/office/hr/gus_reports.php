<?php
$contractLabels = ['uop' => 'Umowa o pracę', 'uz' => 'Umowa zlecenie', 'uod' => 'Umowa o dzieło'];
$n2 = fn($v) => number_format((float)$v, 2, ',', ' ');
$quarterNames = [1 => 'I kwartał', 2 => 'II kwartał', 3 => 'III kwartał', 4 => 'IV kwartał'];
?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            Raporty GUS
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Raporty GUS — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <form method="GET" style="display:flex;gap:6px;align-items:center;">
            <select name="quarter" class="form-control" style="padding:4px 8px;">
                <?php for ($q=1;$q<=4;$q++): ?>
                <option value="<?= $q ?>" <?= $q===$quarter?'selected':'' ?>><?= $quarterNames[$q] ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-control" style="padding:4px 8px;">
                <?php for ($y=(int)date('Y');$y>=(int)date('Y')-3;$y--): ?>
                <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Pokaż</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<div class="alert" style="background:#f0f9ff;border-left:4px solid #2563eb;margin-bottom:16px;padding:12px 16px;font-size:13px;">
    <strong>GUS Z-06:</strong> Kwartalne sprawozdanie o pracujących, wynagrodzeniach i czasie pracy.
    Dotyczy podmiotów zatrudniających ≥10 osób. Dane poniżej stanowią podstawę do wypełnienia formularza.
</div>

<h3 style="margin-bottom:12px;"><?= $quarterNames[$quarter] ?> <?= $year ?></h3>

<!-- Employment Data -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Pracujący (koniec kwartału)</div>
        <div style="font-size:26px;font-weight:700;color:var(--primary);"><?= $gusData['employees_end_q'] ?></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Przyjęci w kwartale</div>
        <div style="font-size:26px;font-weight:700;color:var(--success);"><?= $gusData['new_hires'] ?></div>
    </div>
    <div class="card" style="padding:14px 18px;">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Zwolnieni w kwartale</div>
        <div style="font-size:26px;font-weight:700;color:var(--danger);"><?= $gusData['departures'] ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    <!-- Contract breakdown -->
    <div class="card">
        <div class="card-header"><strong>Struktura zatrudnienia wg typu umowy</strong></div>
        <div class="card-body" style="padding:0;">
            <table class="table" style="margin:0;font-size:13px;">
                <thead><tr><th>Typ umowy</th><th style="text-align:right;">Liczba</th></tr></thead>
                <tbody>
                    <?php foreach ($gusData['contract_breakdown'] as $cb): ?>
                    <tr>
                        <td><?= $contractLabels[$cb['contract_type']] ?? $cb['contract_type'] ?></td>
                        <td style="text-align:right;font-weight:600;"><?= $cb['cnt'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($gusData['contract_breakdown'])): ?>
                    <tr><td colspan="2" class="text-muted">Brak danych</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Wage data -->
    <div class="card">
        <div class="card-header"><strong>Dane płacowe (ostatni miesiąc kwartału)</strong></div>
        <div class="card-body">
            <?php $wd = $gusData['wage_data']; ?>
            <?php if ($wd && $wd['emp_count']): ?>
            <div style="display:grid;gap:12px;">
                <div>
                    <span style="font-size:12px;color:var(--text-muted);">{Łączne wynagrodzenia brutto:</span>
                    <div style="font-size:18px;font-weight:700;"><?= $n2($wd['total_gross']) ?> PLN</div>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--text-muted);">Średnie wynagrodzenie brutto:</span>
                    <div style="font-size:18px;font-weight:700;"><?= $n2($wd['avg_gross']) ?> PLN</div>
                </div>
                <div>
                    <span style="font-size:12px;color:var(--text-muted);">Liczba pracowników na payroll:</span>
                    <div style="font-size:18px;font-weight:700;"><?= $wd['emp_count'] ?></div>
                </div>
            </div>
            <?php else: ?>
            <p class="text-muted">Brak danych płacowych za ostatni miesiąc kwartału.</p>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', ' ');
$period = sprintf('%02d/%04d', $month, $year);
$totalSalesVat = 0;
foreach ($vatSalesSummary as $vs) $totalSalesVat += (float)$vs['vat'];
$costVat = (float)($costVatTotal['vat'] ?? 0);
$costNet = (float)($costVatTotal['net'] ?? 0);
$costGross = (float)($costVatTotal['gross'] ?? 0);
$vatBalance = $totalSalesVat - $costVat;
?>

<div style="display:flex; align-items:center; gap:16px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="/office/clients" class="btn" style="padding:6px 12px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div>
        <h1 style="margin:0;">Rozliczenie VAT</h1>
        <div style="font-size:14px; color:var(--gray-500);"><?= htmlspecialchars($client['company_name']) ?> &middot; NIP: <?= htmlspecialchars($client['nip'] ?? '-') ?></div>
    </div>
</div>

<!-- Period Selector -->
<div class="form-card" style="padding:16px; margin-bottom:24px;">
    <form method="GET" action="/office/clients/<?= $client['id'] ?>/vat-settlement" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <label class="form-label" style="margin:0; font-weight:600;">Okres:</label>
        <select name="month" class="form-input" style="width:auto;">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="form-input" style="width:auto;">
            <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary">Pokaż</button>
        <?php
        $prevM = $month - 1; $prevY = $year;
        if ($prevM < 1) { $prevM = 12; $prevY--; }
        $nextM = $month + 1; $nextY = $year;
        if ($nextM > 12) { $nextM = 1; $nextY++; }
        ?>
        <a href="/office/clients/<?= $client['id'] ?>/vat-settlement?month=<?= $prevM ?>&year=<?= $prevY ?>" class="btn" title="Poprzedni miesiąc">&laquo;</a>
        <a href="/office/clients/<?= $client['id'] ?>/vat-settlement?month=<?= $nextM ?>&year=<?= $nextY ?>" class="btn" title="Następny miesiąc">&raquo;</a>
    </form>
</div>

<!-- VAT Balance Summary -->
<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:24px;">
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #ef4444;">
        <div style="font-size:13px; color:var(--gray-500); margin-bottom:8px;">VAT należny (sprzedaż)</div>
        <div style="font-size:28px; font-weight:700; color:#ef4444;"><?= $fmt($totalSalesVat) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid #16a34a;">
        <div style="font-size:13px; color:var(--gray-500); margin-bottom:8px;">VAT naliczony (koszty)</div>
        <div style="font-size:28px; font-weight:700; color:#16a34a;"><?= $fmt($costVat) ?> PLN</div>
    </div>
    <div class="form-card" style="padding:20px; text-align:center; border-top:3px solid <?= $vatBalance > 0 ? '#ef4444' : '#16a34a' ?>;">
        <div style="font-size:13px; color:var(--gray-500); margin-bottom:8px;"><?= $vatBalance > 0 ? 'Do zapłaty' : 'Do zwrotu / przeniesienia' ?></div>
        <div style="font-size:28px; font-weight:700; color:<?= $vatBalance > 0 ? '#ef4444' : '#16a34a' ?>;"><?= $fmt(abs($vatBalance)) ?> PLN</div>
    </div>
</div>

<!-- Detailed Tables -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px;">
    <!-- VAT należny -->
    <div class="form-card" style="padding:20px;">
        <h3 style="margin-bottom:12px;">VAT należny (sprzedaż) — <?= $period ?></h3>
        <?php if (empty($vatSalesSummary)): ?>
            <p style="color:var(--gray-500);">Brak wystawionych faktur w tym okresie</p>
        <?php else: ?>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th>Stawka VAT</th>
                    <th class="text-right">Netto</th>
                    <th class="text-right">VAT</th>
                    <th class="text-right">Brutto</th>
                </tr>
            </thead>
            <tbody>
                <?php $tNet = 0; $tVat = 0; $tGross = 0; ?>
                <?php foreach ($vatSalesSummary as $vs):
                    $tNet += (float)$vs['net'];
                    $tVat += (float)$vs['vat'];
                    $tGross += (float)$vs['gross'];
                ?>
                <tr>
                    <td><span class="badge" style="background:#fee2e2;color:#991b1b;"><?= htmlspecialchars($vs['rate']) ?>%</span></td>
                    <td class="text-right"><?= $fmt($vs['net']) ?></td>
                    <td class="text-right"><?= $fmt($vs['vat']) ?></td>
                    <td class="text-right"><strong><?= $fmt($vs['gross']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700; background:var(--gray-50);">
                    <td>Razem</td>
                    <td class="text-right"><?= $fmt($tNet) ?></td>
                    <td class="text-right" style="color:#ef4444;"><?= $fmt($tVat) ?></td>
                    <td class="text-right"><?= $fmt($tGross) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>

    <!-- VAT naliczony -->
    <div class="form-card" style="padding:20px;">
        <h3 style="margin-bottom:12px;">VAT naliczony (koszty) — <?= $period ?></h3>
        <?php if ($costNet == 0): ?>
            <p style="color:var(--gray-500);">Brak zaakceptowanych faktur kosztowych w tym okresie</p>
        <?php else: ?>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr><th>Pozycja</th><th class="text-right">Kwota</th></tr>
            </thead>
            <tbody>
                <tr><td>Netto</td><td class="text-right"><?= $fmt($costNet) ?> PLN</td></tr>
                <tr><td>VAT naliczony</td><td class="text-right" style="color:#16a34a; font-weight:700;"><?= $fmt($costVat) ?> PLN</td></tr>
                <tr><td>Brutto</td><td class="text-right"><?= $fmt($costGross) ?> PLN</td></tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 6-month VAT Trend -->
<?php if (!empty($vatTrend)): ?>
<div class="form-card" style="padding:20px;">
    <h3 style="margin-bottom:16px;">Trend VAT — ostatnie 6 miesięcy</h3>
    <div class="table-responsive">
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th>Okres</th>
                    <th class="text-right">VAT należny</th>
                    <th class="text-right">VAT naliczony</th>
                    <th class="text-right">Bilans</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vatTrend as $vt): ?>
                <tr <?= $vt['month'] === $month && $vt['year'] === $year ? 'style="background:var(--blue-50); font-weight:600;"' : '' ?>>
                    <td><?= sprintf('%02d/%04d', $vt['month'], $vt['year']) ?></td>
                    <td class="text-right" style="color:#ef4444;"><?= $fmt($vt['sales_vat']) ?> PLN</td>
                    <td class="text-right" style="color:#16a34a;"><?= $fmt($vt['cost_vat']) ?> PLN</td>
                    <td class="text-right">
                        <strong style="color:<?= $vt['balance'] > 0 ? '#ef4444' : '#16a34a' ?>;">
                            <?= $vt['balance'] > 0 ? '' : '-' ?><?= $fmt(abs($vt['balance'])) ?> PLN
                        </strong>
                        <span style="font-size:11px; color:var(--gray-400);"><?= $vt['balance'] > 0 ? '(do zapłaty)' : '(nadwyżka)' ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width: 900px) {
    div[style*="grid-template-columns:1fr 1fr 1fr"] { grid-template-columns: 1fr !important; }
    div[style*="grid-template-columns:1fr 1fr;"] { grid-template-columns: 1fr !important; }
}
</style>

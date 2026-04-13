<h1><?= $lang('sales_dashboard') ?></h1>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-label"><?= $lang('invoices_issued_count') ?></div>
        <div class="stat-value"><?= $counts['total'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= $lang('total_revenue') ?></div>
        <div class="stat-value"><?= number_format($totalRevenue, 2, ',', ' ') ?> PLN</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= $lang('sales_this_month') ?></div>
        <div class="stat-value"><?= number_format($monthlyRevenue, 2, ',', ' ') ?> PLN</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= $lang('awaiting_ksef') ?></div>
        <div class="stat-value"><?= $counts['issued'] ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="/client/sales/create" class="btn btn-primary"><?= $lang('new_invoice') ?></a>
    <a href="/client/sales" class="btn"><?= $lang('issued_invoices') ?></a>
    <a href="/client/contractors" class="btn"><?= $lang('contractors') ?></a>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">

    <!-- Monthly Sales Chart -->
    <div class="section">
        <h2><?= $lang('monthly_sales') ?></h2>
        <div class="form-card" style="padding:20px;">
            <?php if (empty($monthlySales)): ?>
                <p style="color:var(--gray-500);"><?= $lang('no_sales_data') ?></p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <?php
                    $maxGross = 1;
                    foreach ($monthlySales as $ms) {
                        $maxGross = max($maxGross, (float) $ms['gross']);
                    }
                    $monthNames = ['', 'Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'];
                    ?>
                    <div style="display:flex; align-items:flex-end; gap:4px; height:200px; padding-top:20px;">
                        <?php foreach (array_reverse($monthlySales) as $ms):
                            $height = max(4, round((float) $ms['gross'] / $maxGross * 180));
                        ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; min-width:30px;">
                            <div style="font-size:10px; color:var(--gray-500); margin-bottom:4px;">
                                <?= number_format((float) $ms['gross'], 0, ',', ' ') ?>
                            </div>
                            <div style="width:100%; max-width:40px; height:<?= $height ?>px; background:var(--primary); border-radius:4px 4px 0 0; transition:height 0.3s;" title="<?= number_format((float) $ms['gross'], 2, ',', ' ') ?> PLN"></div>
                            <div style="font-size:10px; color:var(--gray-500); margin-top:4px;">
                                <?= $monthNames[(int) $ms['month']] ?? $ms['month'] ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- VAT Summary -->
    <div class="section">
        <h2><?= $lang('vat_summary') ?></h2>
        <div class="form-card" style="padding:20px;">
            <?php if (empty($vatSummary)): ?>
                <p style="color:var(--gray-500);"><?= $lang('no_sales_data') ?></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= $lang('vat_rate') ?></th>
                            <th style="text-align:right;"><?= $lang('net_amount') ?></th>
                            <th style="text-align:right;"><?= $lang('vat_amount') ?></th>
                            <th style="text-align:right;"><?= $lang('gross_amount') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vatSummary as $vs): ?>
                        <tr>
                            <td><?= htmlspecialchars($vs['rate']) ?>%</td>
                            <td style="text-align:right;"><?= number_format((float) $vs['net'], 2, ',', ' ') ?></td>
                            <td style="text-align:right;"><?= number_format((float) $vs['vat'], 2, ',', ' ') ?></td>
                            <td style="text-align:right; font-weight:600;"><?= number_format((float) $vs['gross'], 2, ',', ' ') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- JPK Download -->
            <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--gray-200);">
                <h3 style="margin-bottom:8px;"><?= $lang('download_jpk_sales') ?></h3>
                <form method="GET" action="/client/sales/jpk" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <select name="month" class="form-input" style="width:auto;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="year" class="form-input" style="width:auto;">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><?= $lang('download_jpk_sales') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Top Buyers -->
<div class="section" style="margin-top:24px;">
    <h2><?= $lang('top_buyers') ?></h2>
    <div class="form-card" style="padding:20px;">
        <?php if (empty($topBuyers)): ?>
            <p style="color:var(--gray-500);"><?= $lang('no_sales_data') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= $lang('buyer_name') ?></th>
                            <th><?= $lang('nip') ?></th>
                            <th style="text-align:center;"><?= $lang('invoices_issued_count') ?></th>
                            <th style="text-align:right;"><?= $lang('total_revenue') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topBuyers as $i => $tb): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($tb['buyer_name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($tb['buyer_nip'] ?? '-') ?></code></td>
                            <td style="text-align:center;"><?= $tb['invoice_count'] ?></td>
                            <td style="text-align:right; font-weight:600;"><?= number_format((float) $tb['total_gross'], 2, ',', ' ') ?> PLN</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

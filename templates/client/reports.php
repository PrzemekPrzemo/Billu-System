<h1><?= $lang('reports') ?></h1>

<!-- Tab Navigation -->
<div class="tabs">
    <a href="#" class="tab-link active" onclick="showTab('sales', this); return false;">Sprzedaz</a>
    <a href="#" class="tab-link" onclick="showTab('costs', this); return false;">Koszty</a>
    <a href="#" class="tab-link" onclick="showTab('overdue', this); return false;">Przeterminowane</a>
    <a href="#" class="tab-link" onclick="showTab('ksef', this); return false;">KSeF</a>
    <a href="#" class="tab-link" onclick="showTab('downloads', this); return false;">Pobrania</a>
</div>

<!-- ======= TAB: Sales ======= -->
<div id="tab-sales" class="tab-content">
    <div class="charts-row">
        <!-- Monthly Sales Chart -->
        <div class="chart-card">
            <h3>Sprzedaz miesiecznie (ostatnie 12 mies.)</h3>
            <?php if (empty($monthlySales)): ?>
                <p class="text-muted">Brak danych</p>
            <?php else:
                $maxGross = max(array_column($monthlySales, 'gross') ?: [1]);
            ?>
            <div class="bar-chart">
                <?php foreach (array_reverse($monthlySales) as $ms): ?>
                <div class="bar-group">
                    <div class="bar-value"><?= number_format((float)$ms['gross'], 0, ',', ' ') ?></div>
                    <div class="bar-stack" style="height:<?= round(((float)$ms['gross'] / $maxGross) * 100) ?>%;">
                        <div class="bar-segment" style="flex:<?= (float)$ms['net'] ?>; background:var(--primary);"></div>
                        <div class="bar-segment" style="flex:<?= (float)$ms['vat'] ?>; background:var(--info);"></div>
                    </div>
                    <div class="bar-label"><?= sprintf('%02d/%d', $ms['month'], $ms['year'] % 100) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <div class="legend-item"><span class="legend-dot" style="background:var(--primary);"></span> Netto</div>
                <div class="legend-item"><span class="legend-dot" style="background:var(--info);"></span> VAT</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Buyers -->
        <div class="chart-card">
            <h3>Top kontrahenci (sprzedaz)</h3>
            <?php if (empty($topBuyers)): ?>
                <p class="text-muted">Brak danych</p>
            <?php else: ?>
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Kontrahent</th>
                        <th>NIP</th>
                        <th class="text-right">Ilosc</th>
                        <th class="text-right">Wartosc brutto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topBuyers as $tb): ?>
                    <tr>
                        <td><?= htmlspecialchars($tb['buyer_name']) ?></td>
                        <td><code><?= htmlspecialchars($tb['buyer_nip'] ?? '-') ?></code></td>
                        <td class="text-right"><?= $tb['invoice_count'] ?></td>
                        <td class="text-right"><strong><?= number_format((float)$tb['total_gross'], 2, ',', ' ') ?> PLN</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Sales Table -->
    <?php if (!empty($monthlySales)): ?>
    <div class="chart-card" style="margin-top:20px;">
        <h3>Zestawienie miesieczne sprzedazy</h3>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Okres</th>
                        <th class="text-right">Liczba FV</th>
                        <th class="text-right">Netto</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Brutto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalNet = 0; $totalVat = 0; $totalGross = 0; $totalCount = 0; ?>
                    <?php foreach ($monthlySales as $ms):
                        $totalNet += (float)$ms['net'];
                        $totalVat += (float)$ms['vat'];
                        $totalGross += (float)$ms['gross'];
                        $totalCount += (int)$ms['invoice_count'];
                    ?>
                    <tr>
                        <td><?= sprintf('%02d/%04d', $ms['month'], $ms['year']) ?></td>
                        <td class="text-right"><?= $ms['invoice_count'] ?></td>
                        <td class="text-right"><?= number_format((float)$ms['net'], 2, ',', ' ') ?></td>
                        <td class="text-right"><?= number_format((float)$ms['vat'], 2, ',', ' ') ?></td>
                        <td class="text-right"><strong><?= number_format((float)$ms['gross'], 2, ',', ' ') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:700; background:var(--gray-50);">
                        <td>Razem</td>
                        <td class="text-right"><?= $totalCount ?></td>
                        <td class="text-right"><?= number_format($totalNet, 2, ',', ' ') ?></td>
                        <td class="text-right"><?= number_format($totalVat, 2, ',', ' ') ?></td>
                        <td class="text-right"><?= number_format($totalGross, 2, ',', ' ') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ======= TAB: Costs ======= -->
<div id="tab-costs" class="tab-content" style="display:none;">
    <div class="charts-row">
        <!-- Monthly Costs Chart -->
        <div class="chart-card">
            <h3>Koszty miesiecznie (ostatnie 12 mies.)</h3>
            <?php if (empty($monthlyCosts)): ?>
                <p class="text-muted">Brak danych</p>
            <?php else:
                $maxCostGross = max(array_column($monthlyCosts, 'gross_total') ?: [1]);
            ?>
            <div class="bar-chart">
                <?php foreach ($monthlyCosts as $mc): ?>
                <div class="bar-group">
                    <div class="bar-value"><?= number_format((float)$mc['gross_total'], 0, ',', ' ') ?></div>
                    <div class="bar-stack" style="height:<?= $maxCostGross > 0 ? round(((float)$mc['gross_total'] / $maxCostGross) * 100) : 0 ?>%;">
                        <div class="bar-segment bar-accepted" style="flex:<?= (int)$mc['accepted'] ?>;"></div>
                        <div class="bar-segment bar-rejected" style="flex:<?= (int)$mc['rejected'] ?>;"></div>
                        <div class="bar-segment bar-pending" style="flex:<?= (int)$mc['total_count'] - (int)$mc['accepted'] - (int)$mc['rejected'] ?>;"></div>
                    </div>
                    <div class="bar-label"><?= sprintf('%02d/%d', $mc['month'], $mc['year'] % 100) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <div class="legend-item"><span class="legend-dot" style="background:var(--success);"></span> Zaakceptowane</div>
                <div class="legend-item"><span class="legend-dot" style="background:var(--danger);"></span> Odrzucone</div>
                <div class="legend-item"><span class="legend-dot" style="background:var(--warning);"></span> Oczekujace</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Suppliers -->
        <div class="chart-card">
            <h3>Top dostawcy (koszty)</h3>
            <?php if (empty($topSellers)): ?>
                <p class="text-muted">Brak danych</p>
            <?php else: ?>
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Dostawca</th>
                        <th>NIP</th>
                        <th class="text-right">Ilosc</th>
                        <th class="text-right">Wartosc brutto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topSellers as $ts): ?>
                    <tr>
                        <td><?= htmlspecialchars($ts['seller_name']) ?></td>
                        <td><code><?= htmlspecialchars($ts['seller_nip'] ?? '-') ?></code></td>
                        <td class="text-right"><?= $ts['invoice_count'] ?></td>
                        <td class="text-right"><strong><?= number_format((float)$ts['total_gross'], 2, ',', ' ') ?> PLN</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Costs Table -->
    <?php if (!empty($monthlyCosts)): ?>
    <div class="chart-card" style="margin-top:20px;">
        <h3>Zestawienie miesieczne kosztow</h3>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Okres</th>
                        <th class="text-right">Liczba FV</th>
                        <th class="text-right">Zaakceptowane</th>
                        <th class="text-right">Odrzucone</th>
                        <th class="text-right">Netto</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Brutto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyCosts as $mc): ?>
                    <tr>
                        <td><?= sprintf('%02d/%04d', $mc['month'], $mc['year']) ?></td>
                        <td class="text-right"><?= $mc['total_count'] ?></td>
                        <td class="text-right"><span class="badge badge-success"><?= $mc['accepted'] ?></span></td>
                        <td class="text-right"><span class="badge badge-error"><?= $mc['rejected'] ?></span></td>
                        <td class="text-right"><?= number_format((float)$mc['net_total'], 2, ',', ' ') ?></td>
                        <td class="text-right"><?= number_format((float)$mc['vat_total'], 2, ',', ' ') ?></td>
                        <td class="text-right"><strong><?= number_format((float)$mc['gross_total'], 2, ',', ' ') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ======= TAB: Overdue ======= -->
<div id="tab-overdue" class="tab-content" style="display:none;">
    <div class="chart-card">
        <h3>Przeterminowane platnosci</h3>
        <?php if (empty($overdueInvoices)): ?>
            <div class="empty-state" style="box-shadow:none;">
                <p>Brak przeterminowanych faktur</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Nr faktury</th>
                        <th>Nabywca</th>
                        <th class="text-right">Kwota brutto</th>
                        <th>Termin platnosci</th>
                        <th>Dni po terminie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueInvoices as $oi): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($oi['invoice_number']) ?></strong></td>
                        <td><?= htmlspecialchars($oi['buyer_name']) ?></td>
                        <td class="text-right"><?= number_format((float)$oi['gross_amount'], 2, ',', ' ') ?> PLN</td>
                        <td><?= htmlspecialchars($oi['due_date']) ?></td>
                        <td>
                            <?php $days = (int)$oi['days_overdue']; ?>
                            <span class="badge <?= $days > 30 ? 'badge-error' : ($days > 14 ? 'badge-warning' : 'badge-warning-orange') ?>">
                                <?= $days ?> dni
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======= TAB: KSeF ======= -->
<div id="tab-ksef" class="tab-content" style="display:none;">
    <!-- Summary Cards -->
    <?php
    $ksefImportSummary = array_filter($ksefSummary ?? [], fn($s) => $s['operation'] === 'import_batch');
    $ksefSendSummary = array_filter($ksefSummary ?? [], fn($s) => in_array($s['operation'], ['invoice_submit', 'invoice_send']));
    $ksefDownloadSummary = array_filter($ksefSummary ?? [], fn($s) => in_array($s['operation'], ['invoice_download', 'invoice_download_raw']));
    $totalImports = array_sum(array_column($ksefImportSummary, 'total'));
    $successImports = array_sum(array_column($ksefImportSummary, 'success_count'));
    $failedImports = array_sum(array_column($ksefImportSummary, 'failed_count'));
    $totalSends = array_sum(array_column($ksefSendSummary, 'total'));
    $successSends = array_sum(array_column($ksefSendSummary, 'success_count'));
    $failedSends = array_sum(array_column($ksefSendSummary, 'failed_count'));
    $totalDownloads = array_sum(array_column($ksefDownloadSummary, 'total'));
    $successDownloads = array_sum(array_column($ksefDownloadSummary, 'success_count'));
    $failedDownloads = array_sum(array_column($ksefDownloadSummary, 'failed_count'));
    ?>
    <div class="charts-row">
        <div class="chart-card">
            <h3>Pobrania z KSeF (import)</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; text-align:center; padding:16px 0;">
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--primary);"><?= $totalImports ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Razem operacji</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--success);"><?= $successImports ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Sukces</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--danger);"><?= $failedImports ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Błędy</div>
                </div>
            </div>
        </div>
        <div class="chart-card">
            <h3>Wysyłki do KSeF</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; text-align:center; padding:16px 0;">
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--primary);"><?= $totalSends ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Razem operacji</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--success);"><?= $successSends ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Sukces</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--danger);"><?= $failedSends ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Błędy</div>
                </div>
            </div>
        </div>
        <div class="chart-card">
            <h3>Pobrania faktur XML</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; text-align:center; padding:16px 0;">
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--primary);"><?= $totalDownloads ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Razem operacji</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--success);"><?= $successDownloads ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Sukces</div>
                </div>
                <div>
                    <div style="font-size:24px; font-weight:700; color:var(--danger);"><?= $failedDownloads ?></div>
                    <div style="font-size:12px; color:var(--gray-500);">Błędy</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary -->
    <?php if (!empty($ksefSummary)): ?>
    <div class="chart-card" style="margin-top:20px;">
        <h3>Zestawienie miesięczne KSeF</h3>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;">
                <thead>
                    <tr>
                        <th>Okres</th>
                        <th>Operacja</th>
                        <th class="text-right">Razem</th>
                        <th class="text-right">Sukces</th>
                        <th class="text-right">Błędy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ksefSummary as $ks): ?>
                    <tr>
                        <td><?= sprintf('%02d/%04d', $ks['month'], $ks['year']) ?></td>
                        <td>
                            <?php
                            $opBadges = [
                                'import_batch' => ['Import', '#dbeafe', '#2563eb'],
                                'invoice_submit' => ['Wysyłka', '#ffedd5', '#c2410c'],
                                'invoice_download' => ['Pobieranie', '#d1fae5', '#065f46'],
                                'invoice_download_raw' => ['Pobieranie XML', '#d1fae5', '#065f46'],
                            ];
                            $opBadge = $opBadges[$ks['operation']] ?? [ucfirst(str_replace('_', ' ', $ks['operation'])), '#f3f4f6', '#374151'];
                            ?>
                            <span class="badge" style="background:<?= $opBadge[1] ?>;color:<?= $opBadge[2] ?>;"><?= $opBadge[0] ?></span>
                        </td>
                        <td class="text-right"><?= $ks['total'] ?></td>
                        <td class="text-right"><span class="badge badge-success"><?= $ks['success_count'] ?></span></td>
                        <td class="text-right">
                            <?php if ((int)$ks['failed_count'] > 0): ?>
                                <span class="badge badge-error"><?= $ks['failed_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Log -->
    <div class="chart-card" style="margin-top:20px;">
        <h3>Dziennik operacji KSeF</h3>
        <div style="margin-bottom:12px;">
            <select id="ksef-log-filter" onchange="filterKsefLogs()" class="form-input" style="width:auto;min-width:180px;">
                <option value="">Wszystkie operacje</option>
                <option value="import_batch">Import</option>
                <option value="invoice_submit">Wysyłka FV</option>
                <option value="invoice_download">Pobieranie FV</option>
                <option value="authenticate">Autentykacja</option>
                <option value="cert_">Certyfikaty</option>
                <option value="permissions_query">Uprawnienia</option>
            </select>
        </div>
        <?php if (empty($ksefLogs)): ?>
            <div class="empty-state" style="box-shadow:none;"><p>Brak operacji KSeF</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-compact" style="box-shadow:none;" id="ksef-log-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Operacja</th>
                        <th>Status</th>
                        <th class="hide-mobile">Nr KSeF</th>
                        <th class="hide-mobile">Czas</th>
                        <th>Szczegóły</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ksefLogs as $log): ?>
                    <tr data-op="<?= htmlspecialchars($log['operation']) ?>">
                        <td style="white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                        <td>
                            <?php
                            $opLabels = [
                                'import_batch' => ['Import', '#dbeafe', '#2563eb'],
                                'invoice_submit' => ['Wysyłka FV', '#ffedd5', '#c2410c'],
                                'invoice_download' => ['Pobieranie FV', '#d1fae5', '#065f46'],
                                'invoice_download_raw' => ['Pobieranie XML', '#d1fae5', '#065f46'],
                                'authenticate' => ['Autentykacja', '#f3e8ff', '#7c3aed'],
                                'session_open' => ['Sesja', '#e0e7ff', '#4338ca'],
                                'session_close' => ['Zamkn. sesji', '#e0e7ff', '#4338ca'],
                                'cert_enroll_start' => ['Certyfikat', '#fef3c7', '#92400e'],
                                'cert_enroll_poll' => ['Certyfikat', '#fef3c7', '#92400e'],
                                'cert_retrieve' => ['Certyfikat', '#fef3c7', '#92400e'],
                                'cert_revoke' => ['Certyfikat', '#fef3c7', '#92400e'],
                                'cert_limits_check' => ['Certyfikat', '#fef3c7', '#92400e'],
                                'permissions_query' => ['Uprawnienia', '#e0e7ff', '#4338ca'],
                            ];
                            $op = $opLabels[$log['operation']] ?? [ucfirst(str_replace('_', ' ', $log['operation'])), '#f3f4f6', '#374151'];
                            ?>
                            <span class="badge" style="background:<?= $op[1] ?>;color:<?= $op[2] ?>;font-size:11px;"><?= $op[0] ?></span>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span class="badge badge-success">OK</span>
                            <?php elseif ($log['status'] === 'failed'): ?>
                                <span class="badge badge-error">Błąd</span>
                            <?php elseif ($log['status'] === 'started'): ?>
                                <span class="badge badge-warning">Start</span>
                            <?php else: ?>
                                <span class="badge"><?= htmlspecialchars($log['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <?php if (!empty($log['ksef_reference_number'])): ?>
                                <code style="font-size:11px;"><?= htmlspecialchars(substr($log['ksef_reference_number'], 0, 30)) ?></code>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($log['duration_ms']): ?>
                                <?= number_format($log['duration_ms'] / 1000, 1) ?>s
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <span style="color:var(--danger);font-size:12px;" title="<?= htmlspecialchars($log['error_message']) ?>">
                                    <?= htmlspecialchars(mb_substr($log['error_message'], 0, 60)) ?><?= mb_strlen($log['error_message']) > 60 ? '...' : '' ?>
                                </span>
                            <?php elseif (!empty($log['response_summary'])): ?>
                                <span style="font-size:12px;color:var(--gray-500);" title="<?= htmlspecialchars($log['response_summary']) ?>">
                                    <?= htmlspecialchars(mb_substr($log['response_summary'], 0, 60)) ?><?= mb_strlen($log['response_summary']) > 60 ? '...' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======= TAB: Downloads ======= -->
<div id="tab-downloads" class="tab-content" style="display:none;">
    <div class="chart-card">
        <h3>Raporty do pobrania</h3>
        <?php if (empty($reports)): ?>
            <div class="empty-state" style="box-shadow:none;">
                <p><?= $lang('no_reports') ?></p>
            </div>
        <?php else: ?>
        <table class="table table-compact" style="box-shadow:none;">
            <thead>
                <tr>
                    <th><?= $lang('period') ?></th>
                    <th><?= $lang('type') ?></th>
                    <th><?= $lang('created_at') ?></th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><?= sprintf('%02d/%04d', $r['period_month'], $r['period_year']) ?></td>
                    <td>
                        <?php if ($r['report_type'] === 'accepted'): ?>
                            <span class="badge badge-success"><?= $lang('accepted') ?></span>
                        <?php else: ?>
                            <span class="badge badge-error"><?= $lang('rejected') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $r['created_at'] ?></td>
                    <td class="action-buttons">
                        <?php if ($r['pdf_path']): ?>
                            <a href="/client/reports/<?= $r['id'] ?>/download?type=pdf" class="btn btn-xs">PDF</a>
                        <?php endif; ?>
                        <?php if ($r['xls_path']): ?>
                            <a href="/client/reports/<?= $r['id'] ?>/download?type=xls" class="btn btn-xs">XLSX</a>
                        <?php endif; ?>
                        <?php if (!empty($r['xml_path'])): ?>
                            <a href="/client/reports/<?= $r['id'] ?>/download?type=xml" class="btn btn-xs"><?= $lang('download_jpk') ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>


<script>
function showTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(function(t) { t.style.display = 'none'; });
    document.querySelectorAll('.tab-link').forEach(function(l) { l.classList.remove('active'); });
    document.getElementById('tab-' + name).style.display = 'block';
    el.classList.add('active');
}

function filterKsefLogs() {
    var filter = document.getElementById('ksef-log-filter').value;
    var rows = document.querySelectorAll('#ksef-log-table tbody tr');
    rows.forEach(function(row) {
        if (!filter) {
            row.style.display = '';
        } else if (filter.endsWith('_')) {
            // Prefix match (e.g. "cert_" matches "cert_enroll_start", "cert_retrieve", etc.)
            row.style.display = row.dataset.op.indexOf(filter) === 0 ? '' : 'none';
        } else {
            row.style.display = row.dataset.op === filter ? '' : 'none';
        }
    });
}
</script>

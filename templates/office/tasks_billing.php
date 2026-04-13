<h2>Rozliczenia zadań</h2>

<!-- Filters -->
<div style="margin-bottom:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <form method="get" action="/office/tasks/billing" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <select name="client_id" class="form-input" style="width:auto; min-width:180px;" onchange="this.form.submit()">
            <option value=""><?= $lang('all_clients') ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($filterClientId ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="billing_status" class="form-input" style="width:auto;" onchange="this.form.submit()">
            <option value="all" <?= ($filterBillingStatus ?? 'all') === 'all' ? 'selected' : '' ?>>Wszystkie statusy</option>
            <option value="to_invoice" <?= ($filterBillingStatus ?? '') === 'to_invoice' ? 'selected' : '' ?>>Do zafakturowania</option>
            <option value="invoiced" <?= ($filterBillingStatus ?? '') === 'invoiced' ? 'selected' : '' ?>>Zafakturowane</option>
            <option value="none" <?= ($filterBillingStatus ?? '') === 'none' ? 'selected' : '' ?>>Brak rozliczenia</option>
        </select>
    </form>
</div>

<?php if (empty($tasks)): ?>
    <div class="alert alert-info">Brak ukończonych zadań do rozliczenia.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= $lang('client') ?></th>
                    <th><?= $lang('task_title') ?></th>
                    <th>Data zakończenia</th>
                    <th>Płatne</th>
                    <th>Cena (PLN)</th>
                    <th>Status rozliczenia</th>
                    <th><?= $lang('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?= htmlspecialchars($task['client_name'] ?? '') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                            <?php if ($task['description']): ?>
                                <br><small style="color:var(--text-muted);"><?= htmlspecialchars(mb_substr($task['description'], 0, 80)) ?><?= mb_strlen($task['description'] ?? '') > 80 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $task['completed_at'] ? date('d.m.Y H:i', strtotime($task['completed_at'])) : '-' ?></td>
                        <td><?= !empty($task['is_billable']) ? '<span class="badge status-open">Tak</span>' : '<span class="badge status-done">Nie</span>' ?></td>
                        <td><?= $task['task_price'] !== null ? number_format((float)$task['task_price'], 2, ',', ' ') : '-' ?></td>
                        <td>
                            <?php
                            $billingLabels = [
                                'none' => '<span class="badge">Brak</span>',
                                'to_invoice' => '<span class="badge status-open">Do zafakturowania</span>',
                                'invoiced' => '<span class="badge status-done">Zafakturowane</span>',
                            ];
                            echo $billingLabels[$task['billing_status'] ?? 'none'] ?? $billingLabels['none'];
                            ?>
                        </td>
                        <td>
                            <form method="post" action="/office/tasks/<?= $task['id'] ?>/billing" style="display:inline-flex; gap:4px; align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <select name="billing_status" class="form-input" style="width:auto; font-size:12px; padding:2px 6px;">
                                    <option value="none" <?= ($task['billing_status'] ?? 'none') === 'none' ? 'selected' : '' ?>>Brak</option>
                                    <option value="to_invoice" <?= ($task['billing_status'] ?? '') === 'to_invoice' ? 'selected' : '' ?>>Do zafakturowania</option>
                                    <option value="invoiced" <?= ($task['billing_status'] ?? '') === 'invoiced' ? 'selected' : '' ?>>Zafakturowane</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Zapisz</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php
    $totalBillable = 0;
    $totalPrice = 0;
    foreach ($tasks as $t) {
        if (!empty($t['is_billable'])) {
            $totalBillable++;
            $totalPrice += (float)($t['task_price'] ?? 0);
        }
    }
    ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div>
                    <strong>Łącznie zadań:</strong> <?= count($tasks) ?>
                </div>
                <div>
                    <strong>Płatnych:</strong> <?= $totalBillable ?>
                </div>
                <div>
                    <strong>Łączna wartość:</strong> <?= number_format($totalPrice, 2, ',', ' ') ?> PLN
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php $typeLabels = \App\Models\HrBhpTraining::TYPE_LABELS; ?>

<div class="section-header">
    <div>
        <div class="breadcrumb-path" style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <a href="/office/hr/settings"><?= $lang('hr_module') ?></a> &rsaquo;
            <a href="/office/hr/<?= $clientId ?>/employees"><?= htmlspecialchars($client['company_name']) ?></a> &rsaquo;
            Szkolenia BHP
        </div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Szkolenia BHP — <?= htmlspecialchars($client['company_name']) ?>
        </h1>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-primary" onclick="document.getElementById('add-bhp-modal').style.display='flex'">+ Dodaj szkolenie</button>
        <a href="/office/hr/<?= $clientId ?>/medical" class="btn btn-secondary">Badania lekarskie</a>
    </div>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<!-- Alerts -->
<?php if (!empty($alerts)): ?>
<div class="alert" style="background:#fff5f5;border-left:4px solid #dc2626;margin-bottom:16px;padding:12px 16px;">
    <strong>Uwaga!</strong> <?= count($alerts) ?> szkolenie/ń BHP wymaga odnowienia:
    <ul style="margin:6px 0 0;padding-left:20px;font-size:13px;">
        <?php foreach (array_slice($alerts, 0, 5) as $a): ?>
        <li>
            <strong><?= htmlspecialchars($a['employee_name']) ?></strong> —
            <?= $typeLabels[$a['training_type']] ?? $a['training_type'] ?>
            (<?= $a['status'] === 'expired' ? '<span style="color:var(--danger);">wygasło ' . $a['expires_at'] . '</span>' : 'wygasa ' . $a['expires_at'] ?>)
        </li>
        <?php endforeach; ?>
        <?php if (count($alerts) > 5): ?><li>...i <?= count($alerts) - 5 ?> więcej</li><?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($trainings)): ?>
<div class="empty-state"><p>Brak szkoleń BHP. Dodaj pierwsze szkolenie.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;font-size:13px;">
            <thead>
                <tr>
                    <th>Pracownik</th>
                    <th>Typ szkolenia</th>
                    <th>Data ukończenia</th>
                    <th>Ważne do</th>
                    <th>Nr certyfikatu</th>
                    <th>Szkolący</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trainings as $t):
                    $expired  = $t['expires_at'] && strtotime($t['expires_at']) < time();
                    $expiring = $t['expires_at'] && !$expired && strtotime($t['expires_at']) < strtotime('+30 days');
                ?>
                <tr style="<?= $expired ? 'background:#fff5f5;' : ($expiring ? 'background:#fffbeb;' : '') ?>">
                    <td><strong><?= htmlspecialchars($t['employee_name']) ?></strong></td>
                    <td><span class="badge badge-info"><?= $typeLabels[$t['training_type']] ?? $t['training_type'] ?></span></td>
                    <td><?= $t['completed_at'] ?></td>
                    <td>
                        <?php if ($t['expires_at']): ?>
                        <span style="color:<?= $expired ? 'var(--danger)' : ($expiring ? 'var(--warning)' : 'var(--text)') ?>;font-weight:<?= $expired || $expiring ? '600' : '400' ?>;">
                            <?= $t['expires_at'] ?>
                        </span>
                        <?php if ($expired): ?><span class="badge badge-danger" style="font-size:10px;">wygasło</span><?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">bezterminowe</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($t['certificate_number'] ?? '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($t['trainer_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Add BHP Modal -->
<div id="add-bhp-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:500px;margin:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">Dodaj szkolenie BHP</h3>
            <button onclick="document.getElementById('add-bhp-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="/office/hr/<?= $clientId ?>/bhp/create">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label">Pracownik *</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">— Wybierz —</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Typ szkolenia *</label>
                    <select name="training_type" class="form-control" required>
                        <?php foreach ($typeLabels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Data ukończenia *</label>
                        <input type="date" name="completed_at" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ważne do</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Nr certyfikatu</label>
                        <input type="text" name="certificate_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Szkolący</label>
                        <input type="text" name="trainer_name" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Uwagi</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Dodaj szkolenie</button>
            </form>
        </div>
    </div>
</div>
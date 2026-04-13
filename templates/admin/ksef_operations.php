<h1><?= $lang('ksef_operations_log') ?></h1>

<div class="section">
    <h2><?= $lang('ksef_clients_overview') ?></h2>
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('company_name') ?></th>
                <th>NIP</th>
                <th><?= $lang('ksef_auth_method') ?></th>
                <th><?= $lang('environment') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('ksef_last_import') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c):
                $cfg = $configs[$c['id']] ?? null;
                if (!$cfg) continue;
            ?>
            <tr>
                <td><?= htmlspecialchars($c['company_name']) ?></td>
                <td><?= htmlspecialchars($c['nip']) ?></td>
                <td>
                    <?php if ($cfg['auth_method'] === 'certificate'): ?>
                        <span class="badge badge-success"><?= $lang('ksef_auth_certificate') ?></span>
                        <br><small><?= htmlspecialchars($cfg['cert_subject_cn'] ?? '') ?></small>
                    <?php elseif ($cfg['auth_method'] === 'ksef_cert'): ?>
                        <span class="badge badge-success"><?= $lang('ksef_auth_ksef_cert') ?></span>
                        <br><small><?= htmlspecialchars($cfg['cert_ksef_name'] ?? '') ?></small>
                    <?php elseif ($cfg['auth_method'] === 'token'): ?>
                        <span class="badge badge-info"><?= $lang('ksef_auth_token') ?></span>
                    <?php else: ?>
                        <span class="badge"><?= $lang('ksef_auth_none') ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(strtoupper($cfg['ksef_environment'] ?? '-')) ?></td>
                <td>
                    <?php if ($cfg['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-danger"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= htmlspecialchars($cfg['last_import_at'] ?? '-') ?>
                    <?php if ($cfg['last_import_status'] === 'failed'): ?>
                        <br><small class="text-danger"><?= htmlspecialchars(substr($cfg['last_error'] ?? '', 0, 100)) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="section">
    <h2><?= $lang('ksef_operations_history') ?></h2>

    <form method="GET" style="margin-bottom:16px;display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= $lang('client') ?></label>
            <select name="client_id" class="form-input" onchange="this.form.submit()">
                <option value=""><?= $lang('all_clients') ?></option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $selectedClient == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (empty($operations)): ?>
    <div class="empty-state"><p><?= $lang('no_results') ?></p></div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('date') ?></th>
                <th><?= $lang('client') ?></th>
                <th><?= $lang('ksef_operation') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('ksef_duration') ?></th>
                <th><?= $lang('details') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($operations as $op): ?>
            <tr>
                <td style="white-space:nowrap;"><?= htmlspecialchars($op['created_at'] ?? '') ?></td>
                <td><?= htmlspecialchars($op['company_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($op['operation']) ?></td>
                <td>
                    <span class="badge badge-<?= $op['status'] === 'success' ? 'success' : ($op['status'] === 'failed' ? 'danger' : 'warning') ?>">
                        <?= htmlspecialchars($op['status'] ?? '') ?>
                    </span>
                </td>
                <td><?= $op['duration_ms'] ? (htmlspecialchars($op['duration_ms']) . ' ms') : '-' ?></td>
                <td>
                    <?php if ($op['error_message']): ?>
                        <small class="text-danger"><?= htmlspecialchars(substr($op['error_message'], 0, 200)) ?></small>
                    <?php elseif ($op['ksef_reference_number']): ?>
                        <small>Ref: <?= htmlspecialchars($op['ksef_reference_number']) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

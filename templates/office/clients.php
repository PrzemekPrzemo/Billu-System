<h1><?= $lang('clients') ?></h1>

<table class="table">
    <thead>
        <tr>
            <th>NIP</th>
            <th><?= $lang('company_name') ?></th>
            <th class="hide-mobile"><?= $lang('representative') ?></th>
            <th class="hide-mobile">Email</th>
            <th><?= $lang('stats') ?></th>
            <th class="hide-mobile"><?= $lang('last_login') ?></th>
            <th><?= $lang('workflow_status') ?></th>
            <th><?= $lang('notes') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clients)): ?>
            <tr><td colspan="9" class="text-center text-muted"><?= $lang('no_clients') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($clients as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['nip']) ?></strong></td>
            <td><?= htmlspecialchars($c['company_name']) ?></td>
            <td class="hide-mobile"><?= htmlspecialchars($c['representative_name']) ?></td>
            <td class="hide-mobile"><?= htmlspecialchars($c['email']) ?></td>
            <td>
                <span class="badge badge-warning"><?= $c['stats']['pending'] ?? 0 ?> <?= $lang('pending') ?></span>
                <span class="badge badge-success"><?= $c['stats']['accepted'] ?? 0 ?> <?= $lang('accepted') ?></span>
                <span class="badge badge-error"><?= $c['stats']['rejected'] ?? 0 ?> <?= $lang('rejected') ?></span>
            </td>
            <td class="hide-mobile"><?= $c['last_login_at'] ? date('Y-m-d H:i', strtotime($c['last_login_at'])) : '-' ?></td>
            <td>
                <?php
                    $steps = ['import', 'weryfikacja', 'jpk', 'zamkniety'];
                    $stepLabels = ['I', 'W', 'J', 'Z'];
                    $stepTitles = [$lang('workflow_import'), $lang('workflow_verification'), $lang('workflow_jpk'), $lang('workflow_closed')];
                    $currentStep = array_search($c['workflow_status'] ?? 'import', $steps);
                ?>
                <div style="display:flex; align-items:center; gap:3px;">
                    <?php foreach ($steps as $si => $s): ?>
                    <div style="width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; <?= $si <= $currentStep ? 'background:var(--success); color:#fff;' : 'background:var(--gray-200); color:var(--gray-500);' ?>" title="<?= $stepTitles[$si] ?>"><?= $stepLabels[$si] ?></div>
                    <?php endforeach; ?>
                    <?php if ($currentStep < count($steps) - 1): ?>
                    <form method="POST" action="/office/clients/<?= $c['id'] ?>/status" style="display:inline; margin-left:4px;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="btn btn-xs" title="<?= $lang('workflow_advanced') ?>" style="padding:2px 6px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <a href="/office/clients/<?= $c['id'] ?>/notes" class="btn btn-xs" title="<?= $lang('internal_notes') ?>" style="display:inline-flex; align-items:center; gap:4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?php if (!empty($c['has_note'])): ?><span class="badge badge-info" style="font-size:10px;">1</span><?php endif; ?>
                </a>
            </td>
            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                <a href="/office/clients/<?= $c['id'] ?>/edit" class="btn btn-xs" title="<?= $lang('edit_client') ?>">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
                <a href="/office/clients/<?= $c['id'] ?>/cost-centers" class="btn btn-xs"><?= $lang('cost_centers_short') ?></a>
                <a href="/office/clients/<?= $c['id'] ?>/vat-settlement" class="btn btn-xs" title="Rozliczenie VAT">VAT</a>
                <?php if (!empty($isEmployee)): ?>
                <form method="POST" action="/office/impersonate-client" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-primary" title="<?= $lang('login_as_client') ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <?= $lang('login_as_client') ?>
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-header">
    <h1><?= $lang('offices') ?></h1>
    <a href="/admin/offices/create" class="btn btn-primary"><?= $lang('add_office') ?></a>
</div>

<table class="table">
    <thead>
        <tr>
            <th>NIP</th>
            <th><?= $lang('office_name') ?></th>
            <th>Email</th>
            <th style="text-align:center;"><?= $lang('clients') ?></th>
            <th><?= $lang('status') ?></th>
            <th><?= $lang('created_at') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($offices)): ?>
            <tr><td colspan="7" class="text-center text-muted"><?= $lang('no_offices') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($offices as $o): ?>
        <tr>
            <td><strong><?= htmlspecialchars($o['nip']) ?></strong></td>
            <td>
                <?= htmlspecialchars($o['name']) ?>
                <?php if (!empty($o['representative_name'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($o['representative_name']) ?></small>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($o['email'] ?? '') ?></td>
            <td style="text-align:center;">
                <?php
                $cliCount = (int) ($o['client_count'] ?? 0);
                $maxCli = $o['max_clients'] ?? null;
                ?>
                <strong><?= $cliCount ?></strong><?php if ($maxCli !== null): ?><span style="color:var(--gray-400);"> / <?= (int) $maxCli ?></span><?php endif; ?>
            </td>
            <td>
                <form method="POST" action="/admin/offices/<?= $o['id'] ?>/toggle-active" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <?php if ($o['is_active']): ?>
                        <button type="submit" class="badge badge-success" style="cursor:pointer; border:none; font:inherit;"
                                data-confirm="<?= $lang('office_deactivate_confirm', ['name' => htmlspecialchars($o['name'])]) ?>">
                            <?= $lang('active') ?>
                        </button>
                    <?php else: ?>
                        <button type="submit" class="badge badge-error" style="cursor:pointer; border:none; font:inherit;"
                                data-confirm="<?= $lang('office_activate_confirm', ['name' => htmlspecialchars($o['name'])]) ?>">
                            <?= $lang('inactive') ?>
                        </button>
                    <?php endif; ?>
                </form>
            </td>
            <td><?= !empty($o['created_at']) ? date('Y-m-d', strtotime($o['created_at'])) : '-' ?></td>
            <td style="white-space:nowrap;">
                <a href="/admin/offices/<?= $o['id'] ?>/edit" class="btn btn-sm"><?= $lang('edit') ?></a>
                <a href="/admin/impersonate/office/<?= $o['id'] ?>" class="btn btn-xs btn-warning"><?= $lang('login_as') ?></a>
                <div style="margin-top:4px; display:flex; gap:4px;">
                    <form method="POST" action="/admin/offices/<?= $o['id'] ?>/enable-invoice-sending" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="btn btn-xs" title="<?= $lang('enable_invoice_sending') ?>" onclick="return confirm('<?= $lang('enable_invoice_sending_confirm') ?>')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <?= $lang('enable_invoice_sending') ?>
                        </button>
                    </form>
                    <form method="POST" action="/admin/offices/<?= $o['id'] ?>/disable-invoice-sending" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="btn btn-xs" title="<?= $lang('disable_invoice_sending') ?>" onclick="return confirm('<?= $lang('disable_invoice_sending_confirm') ?>')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <?= $lang('disable_invoice_sending') ?>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

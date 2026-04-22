<div class="section-header">
    <h1>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        <?= $lang('hr_module') ?> — <?= $lang('hr_access_settings') ?>
    </h1>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <form method="POST" action="/office/hr/settings/enable-all" class="inline-form"
              onsubmit="return confirm('Włączyć moduł HR dla wszystkich klientów?')">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="btn btn-primary">
                <?= $lang('hr_enable_all') ?>
            </button>
        </form>
        <form method="POST" action="/office/hr/settings/disable-all" class="inline-form"
              onsubmit="return confirm('Wyłączyć moduł HR dla wszystkich klientów?')">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="btn btn-danger">
                <?= $lang('hr_disable_all') ?>
            </button>
        </form>
        <span class="text-muted" style="font-size:13px;">
            <?= array_sum(array_column($clients, 'hr_enabled')) ?> / <?= count($clients) ?> <?= $lang('hr_clients_enabled') ?>
        </span>
    </div>
</div>

<?php if (empty($clients)): ?>
<div class="empty-state"><p><?= $lang('no_clients') ?></p></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('company_name') ?></th>
                <th><?= $lang('nip') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('hr_module') ?></th>
                <th><?= $lang('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['company_name']) ?></strong></td>
                <td class="text-muted"><?= htmlspecialchars($c['nip']) ?></td>
                <td>
                    <?php if ($c['is_active']): ?>
                        <span class="badge badge-success"><?= $lang('active') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('inactive') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['hr_enabled']): ?>
                        <span class="badge badge-success"><?= $lang('hr_enabled') ?></span>
                    <?php else: ?>
                        <span class="badge badge-default"><?= $lang('hr_disabled') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if ($c['hr_enabled']): ?>
                            <a href="/office/hr/<?= $c['id'] ?>/employees" class="btn btn-xs btn-primary"><?= $lang('hr_employees') ?></a>
                        <?php endif; ?>
                        <form method="POST" action="/office/hr/settings/toggle/<?= $c['id'] ?>" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-xs <?= $c['hr_enabled'] ? 'btn-danger' : 'btn-success' ?>">
                                <?= $c['hr_enabled'] ? $lang('hr_disable') : $lang('hr_enable') ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

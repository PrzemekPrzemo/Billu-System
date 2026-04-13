<h1><?= $lang('demo_management') ?></h1>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php $flashError = \App\Core\Session::getFlash('error'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= $lang($flashError) ?></div>
<?php endif; ?>

<?php if ($lastReset): ?>
<div class="alert alert-info" style="margin-bottom:20px;">
    <?= $lang('demo_last_reset') ?>: <strong><?= htmlspecialchars($lastReset) ?></strong>
</div>
<?php endif; ?>

<!-- Demo accounts table -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span><?= $lang('demo_accounts') ?></span></div>
    <div class="card-body">
        <?php if (empty($demoOffices) && empty($demoClients)): ?>
            <div class="alert alert-warning" style="margin-bottom:16px;">
                <?= $lang('demo_no_accounts') ?>
            </div>
            <form method="POST" action="/admin/demo/reset">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-primary"><?= $lang('demo_create_accounts') ?></button>
            </form>
        <?php else: ?>
            <?php
                $demoCredentials = \App\Core\Session::get('demo_credentials');
                $demoPasswordDisplay = \App\Core\Session::get('demo_password_display');
                \App\Core\Session::remove('demo_credentials');
                \App\Core\Session::remove('demo_password_display');
            ?>

            <?php if ($demoCredentials): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <strong><?= $lang('demo_credentials_info') ?></strong>
            </div>
            <div class="table-responsive" style="margin-bottom:20px;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= $lang('type') ?></th>
                            <th><?= $lang('company_name') ?></th>
                            <th><?= $lang('login') ?></th>
                            <th><?= $lang('password') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demoCredentials as $cred): ?>
                        <tr>
                            <td><span class="badge"><?= htmlspecialchars($cred['type']) ?></span></td>
                            <td><?= htmlspecialchars($cred['name']) ?></td>
                            <td><code><?= htmlspecialchars($cred['login']) ?></code></td>
                            <td><code><?= htmlspecialchars($cred['password']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($demoPasswordDisplay): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <?= $lang('demo_new_password') ?>: <code style="font-size:1.1em;"><?= htmlspecialchars($demoPasswordDisplay) ?></code>
            </div>
            <?php endif; ?>

            <h3 style="margin-bottom:12px;"><?= $lang('demo_office_accounts') ?></h3>
            <div class="table-responsive" style="margin-bottom:20px;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= $lang('type') ?></th>
                            <th><?= $lang('name') ?></th>
                            <th>NIP / Email</th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demoOffices as $o): ?>
                        <tr>
                            <td><span class="badge badge-info">Biuro</span></td>
                            <td><?= htmlspecialchars($o['name']) ?></td>
                            <td><code><?= htmlspecialchars($o['email']) ?></code></td>
                            <td><?= $o['id'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($demoEmployees as $e): ?>
                        <tr>
                            <td><span class="badge">Pracownik</span></td>
                            <td><?= htmlspecialchars($e['name']) ?></td>
                            <td><code><?= htmlspecialchars($e['email']) ?></code></td>
                            <td><?= $e['id'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-bottom:12px;"><?= $lang('demo_client_accounts') ?></h3>
            <div class="table-responsive" style="margin-bottom:20px;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= $lang('company_name') ?></th>
                            <th>NIP (<?= $lang('login') ?>)</th>
                            <th>Email</th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demoClients as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['company_name']) ?></td>
                            <td><code><?= htmlspecialchars($c['nip']) ?></code></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td><?= $c['id'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Data counts -->
            <?php if (!empty($dataCounts)): ?>
            <h3 style="margin-bottom:12px;"><?= $lang('demo_data_summary') ?></h3>
            <div class="stats-grid" style="margin-bottom:24px;">
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['invoices'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('invoices') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['issued_invoices'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('issued_invoices') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['messages'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('messages') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['client_tasks'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('tasks') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['tax_payments'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('tax_payments') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dataCounts['contractors'] ?? 0 ?></div>
                    <div class="stat-label"><?= $lang('contractors') ?></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Actions -->
<?php if (!empty($demoOffices) || !empty($demoClients)): ?>
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;" class="responsive-grid-2">
    <!-- Reset demo data -->
    <div class="card">
        <div class="card-header"><span><?= $lang('demo_reset_data') ?></span></div>
        <div class="card-body">
            <p style="margin-bottom:12px; color:var(--text-muted);"><?= $lang('demo_reset_description') ?></p>
            <form method="POST" action="/admin/demo/reset" onsubmit="return confirm('<?= $lang('demo_reset_confirm') ?>');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-danger"><?= $lang('demo_reset_button') ?></button>
            </form>
        </div>
    </div>

    <!-- Change demo passwords -->
    <div class="card">
        <div class="card-header"><span><?= $lang('demo_change_passwords') ?></span></div>
        <div class="card-body">
            <p style="margin-bottom:12px; color:var(--text-muted);"><?= $lang('demo_password_description') ?></p>
            <form method="POST" action="/admin/demo/passwords">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label"><?= $lang('new_password') ?></label>
                    <input type="text" name="new_password" class="form-input" required minlength="8" placeholder="Min. 8 znakow">
                </div>
                <button type="submit" class="btn btn-primary"><?= $lang('demo_change_password_button') ?></button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

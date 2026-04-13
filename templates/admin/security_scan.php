<div class="section-header" style="margin-bottom:24px;">
    <div>
        <h1><?= $lang('security_scan') ?></h1>
        <p style="color:var(--gray-500); font-size:14px; margin-top:4px;">
            <?= $lang('security_scan_description') ?>
        </p>
    </div>
    <form method="POST" action="/admin/security-scan" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="btn btn-primary"><?= $lang('run_security_scan') ?></button>
    </form>
</div>

<?php if (!empty($flash_success)): ?>
    <div class="alert alert-success"><?= $lang($flash_success) ?></div>
<?php endif; ?>

<?php if ($lastScan): ?>
    <p style="font-size:13px; color:var(--gray-500); margin-bottom:16px;">
        <?= $lang('scan_last_run') ?>: <strong><?= htmlspecialchars($lastScan['created_at']) ?></strong>
    </p>
<?php else: ?>
    <p style="font-size:13px; color:var(--gray-400); margin-bottom:16px;"><?= $lang('scan_never_run') ?></p>
<?php endif; ?>

<?php if ($summary !== null): ?>
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:12px; margin-bottom:24px;">
    <div class="form-card" style="padding:16px; text-align:center;">
        <div style="font-size:28px; font-weight:700;"><?= $summary['total'] ?></div>
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('scan_total_checks') ?></div>
    </div>
    <div class="form-card" style="padding:16px; text-align:center; border-left:3px solid #22c55e;">
        <div style="font-size:28px; font-weight:700; color:#22c55e;"><?= $summary['pass'] ?></div>
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('scan_pass') ?></div>
    </div>
    <div class="form-card" style="padding:16px; text-align:center; border-left:3px solid #eab308;">
        <div style="font-size:28px; font-weight:700; color:#eab308;"><?= $summary['warn'] ?></div>
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('scan_warn') ?></div>
    </div>
    <div class="form-card" style="padding:16px; text-align:center; border-left:3px solid #ef4444;">
        <div style="font-size:28px; font-weight:700; color:#ef4444;"><?= $summary['fail'] ?></div>
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('scan_fail') ?></div>
    </div>
    <?php if (($summary['ignored'] ?? 0) > 0): ?>
    <div class="form-card" style="padding:16px; text-align:center; border-left:3px solid #9ca3af;">
        <div style="font-size:28px; font-weight:700; color:#9ca3af;"><?= $summary['ignored'] ?></div>
        <div style="font-size:13px; color:var(--gray-500);"><?= $lang('scan_ignored') ?></div>
    </div>
    <?php endif; ?>
</div>

<div class="form-card" style="padding:0; overflow:hidden;">
    <table class="data-table" style="margin:0;">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th><?= $lang('scan_check_name') ?></th>
                <th style="width:100px;"><?= $lang('scan_category') ?></th>
                <th style="width:100px;"><?= $lang('status') ?></th>
                <th><?= $lang('scan_details') ?></th>
                <th><?= $lang('scan_recommendation') ?></th>
                <th style="width:90px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $i => $r): ?>
            <tr<?= $r['status'] === 'ignored' ? ' style="opacity:0.5;"' : '' ?>>
                <td><?= $i + 1 ?></td>
                <td><strong><?= $lang('scan_' . $r['name']) ?></strong></td>
                <td>
                    <span style="font-size:11px; background:var(--gray-100); padding:2px 8px; border-radius:10px;">
                        <?= $lang('scan_cat_' . $r['category']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($r['status'] === 'pass'): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;color:#22c55e;font-weight:600;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= $lang('scan_pass') ?>
                        </span>
                    <?php elseif ($r['status'] === 'warn'): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;color:#eab308;font-weight:600;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <?= $lang('scan_warn') ?>
                        </span>
                    <?php elseif ($r['status'] === 'ignored'): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;color:#9ca3af;font-weight:600;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            <?= $lang('scan_ignored') ?>
                        </span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;color:#ef4444;font-weight:600;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= $lang('scan_fail') ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['details']) ?></td>
                <td style="font-size:13px; color:var(--gray-500);">
                    <?= !empty($r['recommendation']) ? htmlspecialchars($r['recommendation']) : '—' ?>
                </td>
                <td>
                    <?php if ($r['status'] !== 'pass'): ?>
                    <form method="POST" action="/admin/security-scan/ignore" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="check_name" value="<?= htmlspecialchars($r['name']) ?>">
                        <?php if ($r['status'] === 'ignored'): ?>
                            <input type="hidden" name="action" value="unignore">
                            <button type="submit" class="btn btn-sm" title="<?= $lang('scan_unignore') ?>" style="font-size:11px; padding:2px 8px;">
                                <?= $lang('scan_unignore') ?>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="ignore">
                            <button type="submit" class="btn btn-sm" title="<?= $lang('scan_ignore_btn') ?>" style="font-size:11px; padding:2px 8px;"
                                    onclick="return confirm('<?= $lang('scan_ignore_confirm') ?>')">
                                <?= $lang('scan_ignore_btn') ?>
                            </button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($lastScan === null): ?>
<div class="form-card" style="padding:40px; text-align:center; color:var(--gray-400);">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </svg>
    <p><?= $lang('scan_click_to_start') ?></p>
</div>
<?php endif; ?>

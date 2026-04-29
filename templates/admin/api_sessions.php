<div class="section-header">
    <h1><?= $lang('api_sessions') ?></h1>
    <div>
        <a href="/admin/api-settings" class="btn btn-secondary btn-sm">
            &larr; <?= $lang('back_to_settings') ?>
        </a>
    </div>
</div>

<?php if (!empty($sessions)): ?>
<div style="margin-bottom:1rem;display:flex;justify-content:flex-end;">
    <form method="POST" action="/admin/api/sessions/revoke-all"
          onsubmit="return confirm('<?= $lang('revoke_all_sessions_confirm') ?>')">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <button type="submit" class="btn btn-danger btn-sm">
            <?= $lang('revoke_all_sessions') ?>
        </button>
    </form>
</div>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>NIP</th>
            <th><?= $lang('company_name') ?></th>
            <th><?= $lang('device') ?></th>
            <th>IP</th>
            <th><?= $lang('logged_in_at') ?></th>
            <th><?= $lang('expires_at') ?></th>
            <th><?= $lang('actions') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($sessions)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <?= $lang('no_active_sessions') ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($sessions as $s): ?>
        <?php
            $expiresAt  = strtotime($s['expires_at']);
            $daysLeft   = (int) ceil(($expiresAt - time()) / 86400);
            $createdAt  = strtotime($s['created_at']);
            $timeAgo    = time() - $createdAt;
            if ($timeAgo < 3600) {
                $agoStr = round($timeAgo / 60) . ' min temu';
            } elseif ($timeAgo < 86400) {
                $agoStr = round($timeAgo / 3600) . ' godz. temu';
            } else {
                $agoStr = round($timeAgo / 86400) . ' dni temu';
            }
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($s['nip']) ?></strong></td>
            <td>
                <a href="/admin/clients/<?= (int) $s['client_id'] ?>/edit">
                    <?= htmlspecialchars($s['company_name']) ?>
                </a>
            </td>
            <td><?= htmlspecialchars($s['device_name'] ?: '-') ?></td>
            <td><code><?= htmlspecialchars($s['ip_address'] ?: '-') ?></code></td>
            <td title="<?= htmlspecialchars($s['created_at']) ?>"><?= $agoStr ?></td>
            <td>
                <span class="badge <?= $daysLeft <= 7 ? 'badge-warning' : 'badge-success' ?>">
                    <?= $daysLeft ?> dni
                </span>
            </td>
            <td>
                <form method="POST" action="/admin/api/sessions/<?= (int) $s['id'] ?>/revoke"
                      onsubmit="return confirm('<?= $lang('revoke_session_confirm') ?>')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="btn btn-xs btn-danger">
                        <?= $lang('revoke') ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h1><?= $lang('ksef_certificates_title') ?></h1>

<div class="section">
    <a href="/client/ksef" class="btn btn-secondary btn-sm" style="margin-bottom:16px;">&larr; <?= $lang('ksef_configuration') ?></a>

    <?php if ($certificates && !empty($certificates['certificates'])): ?>
    <div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th><?= $lang('ksef_cert_ksef_name') ?></th>
                <th><?= $lang('ksef_cert_type') ?></th>
                <th><?= $lang('ksef_cert_ksef_serial') ?></th>
                <th><?= $lang('status') ?></th>
                <th><?= $lang('ksef_cert_valid') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($certificates['certificates'] as $cert): ?>
            <tr>
                <td><?= htmlspecialchars($cert['certificateName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($cert['certificateType'] ?? '-') ?></td>
                <td><code style="font-size:0.75rem;"><?= htmlspecialchars($cert['certificateSerialNumber'] ?? '-') ?></code></td>
                <td>
                    <?php
                    $status = $cert['status'] ?? 'Unknown';
                    $badgeClass = match($status) {
                        'Active' => 'badge-success',
                        'Blocked', 'Revoked' => 'badge-danger',
                        'Expired' => 'badge-warning',
                        default => 'badge-secondary',
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                </td>
                <td style="font-size:0.85rem;">
                    <?= htmlspecialchars($cert['validFrom'] ?? '-') ?><br>
                    <?= htmlspecialchars($cert['validTo'] ?? '-') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php elseif ($certificates !== null): ?>
    <div class="alert alert-info"><?= $lang('ksef_no_certificates') ?></div>
    <?php else: ?>
    <div class="alert alert-warning"><?= $lang('ksef_cert_query_failed') ?></div>
    <?php endif; ?>
</div>

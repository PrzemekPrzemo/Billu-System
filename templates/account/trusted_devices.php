<?php
$flashSuccess = \App\Core\Session::getFlash('success');
$csrf = \App\Core\Session::generateCsrfToken();

// Tiny helper local to this view — trim a noisy UA string down to "Browser · OS".
$uaShort = static function (string $ua): string {
    if ($ua === '') return '-';
    $browser = '?';
    foreach ([
        '/Edg(e|A)?\/[\d.]+/' => 'Edge',
        '/Firefox\/[\d.]+/'   => 'Firefox',
        '/OPR\/[\d.]+/'       => 'Opera',
        '/Chrome\/[\d.]+/'    => 'Chrome',
        '/Safari\/[\d.]+/'    => 'Safari',
    ] as $rx => $label) {
        if (preg_match($rx, $ua)) { $browser = $label; break; }
    }
    $os = match (true) {
        str_contains($ua, 'Windows NT') => 'Windows',
        str_contains($ua, 'Macintosh')  => 'macOS',
        str_contains($ua, 'Android')    => 'Android',
        str_contains($ua, 'iPhone')     => 'iOS',
        str_contains($ua, 'Linux')      => 'Linux',
        default                          => '?',
    };
    return "{$browser} · {$os}";
};
?>
<div class="section-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1><?= $lang('trusted_devices') ?></h1>
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-secondary"><?= $lang('back') ?></a>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= $lang($flashSuccess) ?: htmlspecialchars((string) $flashSuccess) ?></div>
<?php endif; ?>

<p class="text-muted" style="margin-bottom:18px;">
    <?= $lang('trusted_devices_intro') ?>
</p>

<?php if (empty($devices)): ?>
    <div class="empty-state form-card" style="padding:24px;text-align:center;">
        <p><?= $lang('trusted_devices_empty') ?></p>
    </div>
<?php else: ?>
    <div class="form-card" style="padding:0;overflow:hidden;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th><?= $lang('device') ?></th>
                    <th>IP</th>
                    <th><?= $lang('added') ?></th>
                    <th><?= $lang('last_used') ?></th>
                    <th><?= $lang('expires') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $d): ?>
                <tr>
                    <td>
                        <div style="font-weight:500;">
                            <?= htmlspecialchars($uaShort($d['user_agent'] ?? '')) ?>
                        </div>
                        <?php if (!empty($d['device_label'])): ?>
                            <div class="text-muted" style="font-size:12px;">
                                <?= htmlspecialchars($d['device_label']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <div><?= htmlspecialchars($d['ip_address'] ?? '-') ?></div>
                        <?php
                          $geoLine = trim(implode(', ', array_filter([
                              $d['geo_city']    ?? null,
                              $d['geo_region']  ?? null,
                              $d['geo_country'] ?? null,
                          ])));
                        ?>
                        <?php if ($geoLine !== ''): ?>
                            <div style="font-size:11px;">
                                <?php if (!empty($d['geo_country_code'])): ?>
                                    <span style="font-weight:600;"><?= htmlspecialchars($d['geo_country_code']) ?></span> ·
                                <?php endif; ?>
                                <?= htmlspecialchars($geoLine) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($d['created_at']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($d['last_used_at'] ?? '-') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($d['expires_at']) ?></td>
                    <td>
                        <form method="POST" action="/trusted-devices/<?= (int)$d['id'] ?>/revoke" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= $lang('revoke') ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="POST" action="/trusted-devices/revoke-all"
          style="margin-top:16px;"
          onsubmit="return confirm('<?= htmlspecialchars($lang('trusted_devices_revoke_all_confirm')) ?>');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-secondary"><?= $lang('trusted_devices_revoke_all') ?></button>
    </form>
<?php endif; ?>

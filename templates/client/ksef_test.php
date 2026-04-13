<h1><?= $lang('ksef_test_connection') ?></h1>

<div class="section">
    <div class="form-card" style="padding:16px;">
        <p><strong><?= $lang('ksef_auth_method') ?>:</strong> <?= htmlspecialchars($lang('ksef_auth_' . $authMethod)) ?></p>

        <?php if ($result['success'] ?? false): ?>
        <div class="alert alert-success">
            <?= $lang('ksef_connection_ok') ?>
        </div>
        <table class="table">
            <tr><th><?= $lang('environment') ?></th><td><?= htmlspecialchars($result['environment'] ?? '-') ?></td></tr>
            <tr><th><?= $lang('api_url') ?></th><td><?= htmlspecialchars($result['api_url'] ?? '-') ?></td></tr>
            <tr><th>NIP</th><td><?= htmlspecialchars($result['nip'] ?? '-') ?></td></tr>
        </table>
        <?php else: ?>
        <div class="alert alert-error">
            <?= $lang('ksef_connection_failed') ?>: <?= htmlspecialchars($result['error'] ?? 'Unknown') ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;">
            <a href="/client/ksef" class="btn btn-secondary"><?= $lang('back') ?></a>
        </div>
    </div>
</div>

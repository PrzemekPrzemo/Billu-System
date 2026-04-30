<?php
/**
 * @var array $configs       — rows from EusConfig::findAllForOffice (joined with clients)
 * @var array $unconfigured  — clients of office without any e-US row yet
 */

$today = new \DateTimeImmutable('today');

/** Returns one of 'green' | 'amber' | 'red' | 'grey' for the UPL-1 traffic light. */
$upl1Light = function (array $cfg) use ($today): string {
    if (($cfg['upl1_status'] ?? '') !== 'active' || empty($cfg['upl1_valid_to'])) {
        return 'grey';
    }
    $validTo = new \DateTimeImmutable((string) $cfg['upl1_valid_to']);
    $diff = (int) $today->diff($validTo)->format('%r%a');
    if ($diff < 0)  return 'red';
    if ($diff < 14) return 'amber';
    return 'green';
};

$lightColors = [
    'green' => '#16a34a',
    'amber' => '#f59e0b',
    'red'   => '#dc2626',
    'grey'  => '#9ca3af',
];
$lightLabels = [
    'green' => 'Aktywne',
    'amber' => 'Wkrótce wygasa',
    'red'   => 'Wygasłe',
    'grey'  => 'Brak / nieaktywne',
];
?>
<h1>e-Urząd Skarbowy</h1>
<p style="color:var(--gray-500); margin-bottom:16px;">
    Konfiguracja klientów dla bramki B (deklaracje, JPK_V7) oraz bramki C (korespondencja KAS).
    Pisma z urzędu i statusy wysyłki pojawiają się jako wiadomości i zadania w istniejącym panelu klienta.
</p>

<?php $flashSuccess = \App\Core\Session::getFlash('success'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (empty($configs) && empty($unconfigured)): ?>
    <div class="card"><div class="card-body">
        <p>Brak klientów w Twoim biurze do skonfigurowania.</p>
    </div></div>
<?php else: ?>

<?php if (!empty($configs)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">Skonfigurowani klienci (<?= count($configs) ?>)</div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th>Firma</th>
                    <th>NIP</th>
                    <th>Środowisko</th>
                    <th>Auth</th>
                    <th>UPL-1</th>
                    <th>Bramki</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($configs as $cfg): ?>
                <?php $light = $upl1Light($cfg); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($cfg['company_name'] ?? ''), ENT_QUOTES) ?></td>
                    <td><code><?= htmlspecialchars((string) ($cfg['nip'] ?? ''), ENT_QUOTES) ?></code></td>
                    <td>
                        <span class="badge" style="background:<?= $cfg['environment'] === 'prod' ? '#dc2626' : ($cfg['environment'] === 'test' ? '#f59e0b' : '#9ca3af') ?>; color:#fff;">
                            <?= htmlspecialchars((string) $cfg['environment'], ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td>
                        <small><?= htmlspecialchars((string) $cfg['auth_method'], ENT_QUOTES) ?></small>
                    </td>
                    <td>
                        <span title="<?= htmlspecialchars($lightLabels[$light], ENT_QUOTES) ?>"
                              style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?= $lightColors[$light] ?>; margin-right:6px;"></span>
                        <?php if (!empty($cfg['upl1_valid_to'])): ?>
                            <small>do <?= htmlspecialchars((string) $cfg['upl1_valid_to'], ENT_QUOTES) ?></small>
                        <?php else: ?>
                            <small style="color:var(--gray-400);">brak</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($cfg['bramka_b_enabled'])): ?>
                            <span class="badge" style="background:#dbeafe; color:#1e40af;">B</span>
                        <?php endif; ?>
                        <?php if (!empty($cfg['bramka_c_enabled'])): ?>
                            <span class="badge" style="background:#dcfce7; color:#15803d;">C</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/office/eus/<?= (int) $cfg['client_id'] ?>/configure" class="btn btn-xs">Edytuj</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($unconfigured)): ?>
<div class="card">
    <div class="card-header">Klienci bez konfiguracji e-US (<?= count($unconfigured) ?>)</div>
    <div class="card-body" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr><th>Firma</th><th>NIP</th><th>Akcje</th></tr>
            </thead>
            <tbody>
            <?php foreach ($unconfigured as $c): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $c['company_name'], ENT_QUOTES) ?></td>
                    <td><code><?= htmlspecialchars((string) $c['nip'], ENT_QUOTES) ?></code></td>
                    <td>
                        <a href="/office/eus/<?= (int) $c['id'] ?>/configure" class="btn btn-xs btn-primary">Skonfiguruj</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

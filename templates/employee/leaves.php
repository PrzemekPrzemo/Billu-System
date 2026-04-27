<?php $flashSuccess = \App\Core\Session::getFlash('success'); $flashError = \App\Core\Session::getFlash('error'); ?>
<div class="section-header">
    <h1>Moje urlopy</h1>
    <a href="/employee/leaves/request" class="btn btn-primary">+ Złóż wniosek</a>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $flashError) ?></div>
<?php endif; ?>

<?php if (empty($leaves)): ?>
    <div class="empty-state">
        <p>Brak wniosków urlopowych.</p>
        <a href="/employee/leaves/request" class="btn btn-primary">Złóż pierwszy wniosek</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Typ urlopu</th>
                    <th>Od</th>
                    <th>Do</th>
                    <th>Dni roboczych</th>
                    <th>Status</th>
                    <th>Notatka</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['leave_type']) ?></td>
                    <td><?= htmlspecialchars($l['start_date']) ?></td>
                    <td><?= htmlspecialchars($l['end_date']) ?></td>
                    <td><?= (int) $l['business_days'] ?></td>
                    <td>
                        <?php
                        $badge = $l['status'] === 'approved' ? 'success' :
                                ($l['status'] === 'rejected' ? 'danger' :
                                ($l['status'] === 'cancelled' ? 'default' : 'warning'));
                        ?>
                        <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($l['status']) ?></span>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars(mb_substr($l['notes'] ?? '', 0, 80)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

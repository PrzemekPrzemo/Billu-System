<?php
$contextLabels = [
    'employee' => 'Pracownik', 'contract' => 'Umowa', 'payroll' => 'Lista płac',
    'leave' => 'Urlop', 'general' => 'Ogólne',
];
$contextBadge = [
    'employee' => 'badge-info', 'contract' => 'badge-secondary', 'payroll' => 'badge-success',
    'leave' => 'badge-warning', 'general' => 'badge-default',
];
?>

<div class="section-header">
    <div>
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Wiadomości HR
        </h1>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('new-msg-modal').style.display='flex'">+ Nowa wiadomość</button>
</div>

<?php include __DIR__ . '/../hr_nav.php'; ?>

<?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

<?php if (empty($messages)): ?>
<div class="empty-state">
    <p>Brak wiadomości HR. Możesz napisać do biura w sprawie kadrowej.</p>
    <button class="btn btn-primary" onclick="document.getElementById('new-msg-modal').style.display='flex'">Napisz wiadomość</button>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php foreach ($messages as $m): ?>
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);<?= !$m['is_read_by_client'] ? 'background:#f0f9ff;' : '' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <div>
                    <span class="badge <?= $contextBadge[$m['hr_context']] ?? 'badge-default' ?>"><?= $contextLabels[$m['hr_context']] ?? 'HR' ?></span>
                    <?php if ($m['hr_employee_name']): ?>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:6px;"><?= htmlspecialchars($m['hr_employee_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($m['subject']): ?>
                    <strong style="margin-left:8px;"><?= htmlspecialchars($m['subject']) ?></strong>
                    <?php endif; ?>
                </div>
                <span style="font-size:11px;color:var(--text-muted);">
                    <?= $m['sender_type'] === 'client' ? 'Ty' : 'Biuro' ?> &middot;
                    <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?>
                </span>
            </div>
            <p style="margin:0;font-size:13px;color:var(--text);"><?= nl2br(htmlspecialchars(mb_substr($m['body'], 0, 200))) ?><?= mb_strlen($m['body']) > 200 ? '...' : '' ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- New Message Modal -->
<div id="new-msg-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:520px;margin:24px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;">Nowa wiadomość HR</h3>
            <button onclick="document.getElementById('new-msg-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" action="/client/hr/messages/create">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Kontekst</label>
                        <select name="hr_context" class="form-control">
                            <?php foreach ($contextLabels as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pracownik <small style="color:var(--text-muted);">(opcjonalnie)</small></label>
                        <select name="hr_employee_id" class="form-control">
                            <option value="0">— Brak —</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Temat</label>
                    <input type="text" name="subject" class="form-control" placeholder="Temat wiadomości">
                </div>

                <div class="form-group">
                    <label class="form-label">Treść *</label>
                    <textarea name="body" class="form-control" rows="4" required placeholder="Opisz sprawę..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Wyślij</button>
            </form>
        </div>
    </div>
</div>

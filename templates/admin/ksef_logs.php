<h1><?= $lang('ksef_debug_logs') ?></h1>

<p class="text-muted" style="margin-bottom:16px;"><?= $lang('ksef_debug_logs_desc') ?></p>

<?php if (empty($sessions)): ?>
    <div class="empty-state">
        <p><?= $lang('no_ksef_logs') ?></p>
    </div>
<?php else: ?>

<div style="display:flex;gap:20px;flex-wrap:wrap;">
    <!-- Session list -->
    <div style="flex:0 0 380px;">
        <h2 style="font-size:16px;margin-bottom:8px;"><?= $lang('ksef_log_sessions') ?></h2>
        <div style="max-height:600px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;">
            <?php foreach ($sessions as $s): ?>
            <a href="/admin/ksef-logs?session=<?= urlencode($s['id']) ?>"
               class="<?= ($selectedSession ?? '') === $s['id'] ? 'ksef-log-active' : '' ?>"
               style="display:block;padding:10px 14px;border-bottom:1px solid #eee;text-decoration:none;color:inherit;<?= ($selectedSession ?? '') === $s['id'] ? 'background:#e8f4f8;font-weight:600;' : '' ?>">
                <div style="font-size:13px;font-family:monospace;"><?= htmlspecialchars($s['id']) ?></div>
                <div style="font-size:12px;color:#666;margin-top:4px;">
                    <?= htmlspecialchars($s['modified'] ?? '') ?>
                    &middot; <?= (int)($s['request_count'] ?? 0) ?> req
                    <?php if (($s['error_count'] ?? 0) > 0): ?>
                        &middot; <span style="color:#dc2626;font-weight:600;"><?= (int)$s['error_count'] ?> <?= $lang('errors') ?></span>
                    <?php endif; ?>
                    &middot; <?= number_format(($s['size'] ?? 0) / 1024, 1) ?> KB
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Log content -->
    <div style="flex:1;min-width:400px;">
        <?php if ($logContent !== null): ?>
        <h2 style="font-size:16px;margin-bottom:8px;">
            <?= $lang('ksef_log_detail') ?>: <code><?= htmlspecialchars($selectedSession) ?></code>
        </h2>
        <pre class="diagnostic-log" style="padding:16px;border-radius:8px;font-size:12px;line-height:1.6;overflow-x:auto;max-height:700px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"><?php
            // Highlight log levels
            $content = htmlspecialchars($logContent);
            $content = preg_replace('/\[ERROR\]/', '<span style="color:#ef4444;font-weight:bold;">[ERROR]</span>', $content);
            $content = preg_replace('/\[INFO\]/', '<span style="color:#22c55e;">[INFO]</span>', $content);
            $content = preg_replace('/\[DEBUG\]/', '<span style="color:#60a5fa;">[DEBUG]</span>', $content);
            $content = preg_replace('/\[REQUEST\]/', '<span style="color:#f59e0b;font-weight:bold;">[REQUEST]</span>', $content);
            $content = preg_replace('/\[RESPONSE\]/', '<span style="color:#a78bfa;font-weight:bold;">[RESPONSE]</span>', $content);
            $content = preg_replace('/(HTTP [45]\d{2})/', '<span style="color:#ef4444;font-weight:bold;">$1</span>', $content);
            $content = preg_replace('/(HTTP [23]\d{2})/', '<span style="color:#22c55e;">$1</span>', $content);
            $content = preg_replace('/(Step \d\/\d (?:OK|FAILED)[^<]*)/', '<span style="font-weight:bold;">$1</span>', $content);
            echo $content;
        ?></pre>
        <?php else: ?>
        <div class="empty-state" style="margin-top:40px;">
            <p><?= $lang('ksef_log_select') ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

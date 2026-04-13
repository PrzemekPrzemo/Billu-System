<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Wyst&#261;pi&#322; b&#322;&#261;d</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container" style="text-align: center; padding: 100px 20px;">
        <h1 style="font-size: 72px; color: #dc3545;">500</h1>
        <p style="font-size: 18px;">Wyst&#261;pi&#322; b&#322;&#261;d wewn&#281;trzny serwera.</p>
        <?php if (isset($errorMessage) && isset($errorFile) && in_array(\App\Core\Session::get('user_type'), ['admin', 'client', 'office'], true)): ?>
        <details style="margin-top: 20px; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
            <summary style="cursor: pointer; color: #666;">Szczeg&#243;&#322;y b&#322;&#281;du</summary>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-top: 10px; overflow-x: auto; font-size: 13px;"><?= htmlspecialchars($errorMessage) ?>

<?= htmlspecialchars($errorFile) ?></pre>
        </details>
        <?php endif; ?>
        <a href="/" class="btn btn-primary" style="margin-top: 20px;">Strona g&#322;&#243;wna</a>
    </div>
</body>
</html>

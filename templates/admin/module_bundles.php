<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1><?= $lang('module_bundles') ?></h1>
        <p style="color:var(--gray-500);margin-top:4px;">Zarządzanie pakietami modułów i przypisywanie do biur rachunkowych</p>
    </div>
</div>

<!-- Assign Bundle to Office -->
<div class="form-card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:16px;">Przypisz pakiet do biura</h3>
    <form method="POST" action="/admin/module-bundles/assign" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div style="flex:1;min-width:200px;">
            <label class="form-label">Biuro rachunkowe</label>
            <select name="office_id" class="form-input" required>
                <option value="">-- wybierz biuro --</option>
                <?php foreach ($offices as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?> (NIP: <?= htmlspecialchars($o['nip'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:200px;">
            <label class="form-label">Pakiet</label>
            <select name="bundle_id" class="form-input" required>
                <option value="">-- wybierz pakiet --</option>
                <?php foreach ($bundles as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> (<?= number_format((float)($b['price_monthly'] ?? 0), 2, ',', ' ') ?> PLN/mies.)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Przypisanie pakietu zmieni konfigurację modułów biura. Kontynuować?')">Przypisz pakiet</button>
    </form>
</div>

<!-- Bundle Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;margin-bottom:32px;">
    <?php foreach ($bundles as $b):
        $moduleSlugs = json_decode($b['modules_json'] ?? '[]', true) ?: [];
    ?>
    <div class="form-card" style="border-left:4px solid var(--primary);<?= empty($b['is_active']) ? 'opacity:0.5;' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 style="margin:0;"><?= htmlspecialchars($b['name']) ?></h3>
            <span style="font-size:18px;font-weight:700;color:var(--primary);">
                <?= number_format((float)($b['price_monthly'] ?? 0), 0, ',', ' ') ?> PLN<span style="font-size:12px;font-weight:400;color:var(--gray-500);">/mies.</span>
            </span>
        </div>
        <p style="color:var(--gray-500);font-size:13px;margin-bottom:12px;"><?= htmlspecialchars($b['description'] ?? '') ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:4px;">
            <?php foreach ($moduleSlugs as $slug): ?>
            <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;background:var(--gray-100);color:var(--gray-600);"><?= htmlspecialchars($slug) ?></span>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--gray-400);">
            <?= count($moduleSlugs) ?> modułów
            <?php if (empty($b['is_active'])): ?>
            · <span style="color:var(--error);">Nieaktywny</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Dependency Map -->
<div class="form-card">
    <h3 style="margin-bottom:16px;">Mapa zależności modułów</h3>
    <p style="color:var(--gray-500);font-size:13px;margin-bottom:16px;">
        Przy włączaniu modułu automatycznie włączane są wymagane zależności.
        Przy wyłączaniu — automatycznie wyłączane są moduły zależne.
    </p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Moduł</th>
                <th>Wymaga (required)</th>
                <th>Rekomendowane</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $allMods = \App\Models\Module::findAll(true);
            foreach ($allMods as $m):
                $deps = $dependencyMap[$m['slug']] ?? [];
                $required = array_filter($deps, fn($d) => $d['type'] === 'required');
                $recommended = array_filter($deps, fn($d) => $d['type'] === 'recommended');
                if (empty($deps)) continue;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($m['name']) ?></strong> <span style="color:var(--gray-400);font-size:11px;">(<?= $m['slug'] ?>)</span></td>
                <td>
                    <?php foreach ($required as $d): ?>
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;background:#fee2e2;color:#991b1b;margin:1px;"><?= htmlspecialchars($d['slug']) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($required)): ?>—<?php endif; ?>
                </td>
                <td>
                    <?php foreach ($recommended as $d): ?>
                    <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;background:#dbeafe;color:#1e40af;margin:1px;"><?= htmlspecialchars($d['slug']) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($recommended)): ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

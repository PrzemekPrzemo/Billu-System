<?php
/**
 * Admin: create / edit advertisement form.
 * $ad    — null for create, array for edit
 * $title — page heading string
 */
$placementLabels = \App\Models\Advertisement::PLACEMENTS;
$typeLabels      = \App\Models\Advertisement::TYPES;
$isEdit = !empty($ad);
$formAction = $isEdit
    ? '/admin/advertisements/' . (int)$ad['id'] . '/update'
    : '/admin/advertisements/create';

$v = fn(string $key, string $default = ''): string =>
    htmlspecialchars($isEdit ? ($ad[$key] ?? $default) : $default);
?>
<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
    <a href="/admin/advertisements" class="btn btn-secondary btn-sm">&larr; Powrót</a>
    <h1 style="font-size:22px; font-weight:700;"><?= htmlspecialchars($title) ?></h1>
</div>

<div class="card" style="max-width:760px;">
    <div class="card-body">
        <form method="post" action="<?= $formAction ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">

            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Tytuł <span style="color:var(--danger);">*</span></label>
                <input type="text" name="title" value="<?= $v('title') ?>"
                       required maxlength="255"
                       style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;"
                       placeholder="np. Nowa funkcja: eksport do Excel">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Placement <span style="color:var(--danger);">*</span></label>
                    <select name="placement" required
                            style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;">
                        <?php foreach ($placementLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($isEdit && $ad['placement'] === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">
                        Gdzie ma się wyświetlać reklama
                    </div>
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Typ</label>
                    <select name="type"
                            style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;">
                        <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($isEdit && $ad['type'] === $key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">
                        Determinuje kolor baneru
                    </div>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; margin-bottom:5px;">Treść <span style="color:var(--danger);">*</span></label>
                <textarea name="content" required rows="4"
                          style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px; resize:vertical;"
                          placeholder="Treść reklamy. Dozwolone proste tagi HTML: <b>, <strong>, <em>, <br>."><?= $v('content') ?></textarea>
                <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">
                    Dozwolone podstawowe tagi HTML (&lt;b&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;a&gt;).
                </div>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">URL linku <span style="color:var(--gray-400); font-weight:400;">(opcjonalny)</span></label>
                    <input type="url" name="link_url" value="<?= $v('link_url') ?>"
                           maxlength="500"
                           style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;"
                           placeholder="https://...">
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Etykieta linku</label>
                    <input type="text" name="link_text" value="<?= $v('link_text') ?>"
                           maxlength="100"
                           style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;"
                           placeholder="Dowiedz się więcej">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 100px; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Wyświetlaj od</label>
                    <input type="datetime-local" name="starts_at"
                           value="<?= $isEdit && $ad['starts_at'] ? htmlspecialchars(substr($ad['starts_at'], 0, 16)) : '' ?>"
                           style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;">
                    <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">Puste = od zaraz</div>
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Wyświetlaj do</label>
                    <input type="datetime-local" name="ends_at"
                           value="<?= $isEdit && $ad['ends_at'] ? htmlspecialchars(substr($ad['ends_at'], 0, 16)) : '' ?>"
                           style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;">
                    <div style="font-size:12px; color:var(--gray-400); margin-top:4px;">Puste = bez końca</div>
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:5px;">Kolejność</label>
                    <input type="number" name="sort_order" min="0" max="9999"
                           value="<?= $v('sort_order', '0') ?>"
                           style="width:100%; padding:9px 12px; border:1px solid var(--gray-300); border-radius:6px; font-size:14px;">
                </div>
            </div>

            <div style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                    <input type="checkbox" name="is_active" value="1"
                           <?= (!$isEdit || $ad['is_active']) ? 'checked' : '' ?>
                           style="width:16px; height:16px;">
                    Aktywna (wyświetlana użytkownikom)
                </label>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Zapisz zmiany' : 'Dodaj reklamę' ?>
                </button>
                <a href="/admin/advertisements" class="btn btn-secondary">Anuluj</a>
            </div>
        </form>
    </div>
</div>

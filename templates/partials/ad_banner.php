<?php
/**
 * Ad banner partial.
 * Expected variables:
 *   $ads   — array of advertisement rows from Advertisement::findActive()
 *   $csrf  — CSRF token (injected automatically by Controller::render())
 */

$dismissedAds = $_SESSION['dismissed_ads'] ?? [];
$minimizedAds = $_SESSION['minimized_ads'] ?? [];

$typeColors = [
    'info'    => ['bg' => '#e0f2fe', 'border' => '#7dd3fc', 'color' => '#075985', 'icon' => 'ℹ️'],
    'promo'   => ['bg' => '#f5f3ff', 'border' => '#c4b5fd', 'color' => '#5b21b6', 'icon' => '🎯'],
    'warning' => ['bg' => '#fef3c7', 'border' => '#fcd34d', 'color' => '#92400e', 'icon' => '⚠️'],
    'success' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#065f46', 'icon' => '✅'],
];

foreach ($ads as $ad):
    $adId = (int) $ad['id'];
    if (!empty($dismissedAds[$adId])) continue;
    $isMinimized = !empty($minimizedAds[$adId]);
    $colors = $typeColors[$ad['type']] ?? $typeColors['info'];
?>
<div class="ad-banner" id="ad-banner-<?= $adId ?>"
     data-ad-id="<?= $adId ?>"
     style="border:1px solid <?= $colors['border'] ?>; border-radius:8px; margin-bottom:12px; overflow:hidden; background:<?= $colors['bg'] ?>;">
    <div class="ad-banner-header"
         style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; cursor:pointer; color:<?= $colors['color'] ?>;"
         onclick="adBannerToggle(<?= $adId ?>)">
        <span style="font-weight:600; font-size:13px;">
            <?= $colors['icon'] ?>&nbsp;<?= htmlspecialchars($ad['title']) ?>
        </span>
        <div style="display:flex; gap:4px; align-items:center;" onclick="event.stopPropagation()">
            <button type="button"
                    class="btn-ghost btn-xs ad-minimize-btn"
                    id="ad-min-btn-<?= $adId ?>"
                    title="<?= $isMinimized ? 'Rozwiń' : 'Minimalizuj' ?>"
                    onclick="adBannerMinimize(event, <?= $adId ?>)"
                    style="font-size:14px; line-height:1; padding:2px 7px; color:<?= $colors['color'] ?>;">
                <?= $isMinimized ? '＋' : '－' ?>
            </button>
            <button type="button"
                    class="btn-ghost btn-xs"
                    title="Zamknij na tę sesję"
                    onclick="adBannerDismiss(event, <?= $adId ?>)"
                    style="font-size:14px; line-height:1; padding:2px 7px; color:<?= $colors['color'] ?>;">
                ✕
            </button>
        </div>
    </div>
    <div class="ad-banner-body"
         id="ad-body-<?= $adId ?>"
         style="padding:0 14px 12px; color:<?= $colors['color'] ?>;<?= $isMinimized ? ' display:none;' : '' ?>">
        <div style="font-size:13.5px; line-height:1.5;">
            <?= $ad['content'] /* admin-only content, not user-supplied */ ?>
        </div>
        <?php if (!empty($ad['link_url'])): ?>
        <div style="margin-top:8px;">
            <a href="<?= htmlspecialchars($ad['link_url']) ?>"
               style="font-size:13px; font-weight:600; color:<?= $colors['color'] ?>; text-decoration:underline;"
               target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars($ad['link_text'] ?: 'Dowiedz się więcej') ?> →
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
(function() {
    var csrfToken = <?= json_encode($csrf) ?>;

    function adPost(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_token=' + encodeURIComponent(csrfToken)
        }).then(function(r) { return r.json(); });
    }

    window.adBannerDismiss = function(e, id) {
        e.stopPropagation();
        adPost('/ads/' + id + '/dismiss').then(function(data) {
            if (data.ok) {
                var el = document.getElementById('ad-banner-' + id);
                if (el) {
                    el.style.transition = 'opacity 0.3s';
                    el.style.opacity = '0';
                    setTimeout(function() { el.style.display = 'none'; }, 320);
                }
            }
        });
    };

    window.adBannerMinimize = function(e, id) {
        e.stopPropagation();
        var body = document.getElementById('ad-body-' + id);
        var btn  = document.getElementById('ad-min-btn-' + id);
        var isNowMinimized = body && body.style.display === 'none';

        if (isNowMinimized) {
            adPost('/ads/' + id + '/restore').then(function(data) {
                if (data.ok && body) {
                    body.style.display = '';
                    if (btn) { btn.textContent = '－'; btn.title = 'Minimalizuj'; }
                }
            });
        } else {
            adPost('/ads/' + id + '/minimize').then(function(data) {
                if (data.ok && body) {
                    body.style.display = 'none';
                    if (btn) { btn.textContent = '＋'; btn.title = 'Rozwiń'; }
                }
            });
        }
    };

    window.adBannerToggle = function(id) {
        adBannerMinimize({ stopPropagation: function(){} }, id);
    };
}());
</script>

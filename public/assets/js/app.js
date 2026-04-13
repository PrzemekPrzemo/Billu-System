/**
 * BiLLU - Frontend JS
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme icon
    updateThemeIcon(document.documentElement.getAttribute('data-theme') === 'dark');

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });

    // Confirm before destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Cookie consent banner
    initCookieConsent();
});

// Session timeout warning (25 min warning before 30 min timeout)
(function() {
    var timeoutMinutes = 30;
    var warningMinutes = 25;
    var warningShown = false;

    setInterval(function() {
        // This is a simple client-side reminder; actual session expiry is server-side
    }, 60000);

    setTimeout(function() {
        if (!warningShown) {
            warningShown = true;
            var msg = document.documentElement.lang === 'pl'
                ? 'Twoja sesja wygaśnie za 5 minut. Zapisz swoją pracę.'
                : 'Your session will expire in 5 minutes. Save your work.';
            var div = document.createElement('div');
            div.className = 'alert alert-warning';
            div.style.position = 'fixed';
            div.style.top = '10px';
            div.style.right = '10px';
            div.style.zIndex = '9999';
            div.style.maxWidth = '350px';
            div.textContent = msg;
            document.body.appendChild(div);
            setTimeout(function() { div.remove(); }, 30000);
        }
    }, warningMinutes * 60 * 1000);
})();

// Dark mode toggle
function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme');
    var next = current === 'dark' ? '' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next || 'light');
    updateThemeIcon(next === 'dark');
}

function updateThemeIcon(isDark) {
    var moon = document.querySelector('.theme-icon-moon');
    var sun = document.querySelector('.theme-icon-sun');
    if (moon && sun) {
        moon.style.display = isDark ? 'none' : 'block';
        sun.style.display = isDark ? 'block' : 'none';
    }
}

// GUS API lookup
function gusLookup() {
    var nip = document.querySelector('input[name="nip"]').value.replace(/\D/g, '');
    if (nip.length !== 10) {
        alert('Wprowadź poprawny NIP (10 cyfr)');
        return;
    }
    var btn = document.getElementById('gus-lookup-btn');
    if (btn) btn.disabled = true;

    fetch('/admin/gus-lookup?nip=' + nip)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (data.Nazwa) document.querySelector('input[name="company_name"]').value = data.Nazwa;
            if (data.formatted_address) document.querySelector('input[name="address"]').value = data.formatted_address;
            if (data.Regon) document.querySelector('input[name="regon"]').value = data.Regon;
            if (data.source === 'ceidg') {
                var info = document.getElementById('gus-source-info');
                if (info) {
                    info.textContent = '(dane pobrano z CEIDG)';
                    info.style.display = 'inline';
                } else if (btn) {
                    var span = document.createElement('span');
                    span.id = 'gus-source-info';
                    span.style.cssText = 'color:var(--info-color,#3498db); font-size:0.85em; margin-left:8px;';
                    span.textContent = '(dane pobrano z CEIDG)';
                    btn.parentNode.insertBefore(span, btn.nextSibling);
                }
            } else {
                var info = document.getElementById('gus-source-info');
                if (info) info.style.display = 'none';
            }
        })
        .catch(function(e) { alert('Błąd połączenia z GUS/CEIDG API'); })
        .finally(function() { if (btn) btn.disabled = false; });
}

// Cookie consent banner
function initCookieConsent() {
    var banner = document.getElementById('cookie-consent');
    var acceptBtn = document.getElementById('cookie-accept');

    if (!banner || !acceptBtn) return;

    // Check if already accepted
    if (localStorage.getItem('cookie_consent') === 'accepted') {
        return; // Keep banner hidden
    }

    // Show banner
    banner.style.display = 'block';

    // Accept handler
    acceptBtn.addEventListener('click', function() {
        localStorage.setItem('cookie_consent', 'accepted');
        banner.style.transition = 'opacity 0.3s, transform 0.3s';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(100%)';
        setTimeout(function() {
            banner.style.display = 'none';
        }, 300);
    });
}

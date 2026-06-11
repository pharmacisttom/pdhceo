<?php
require_once __DIR__ . '/../config/app_version.php';
$pdhVersion = pdh_app_version_info();
?>

<div class="pdh-version-badge">
    <span class="pdh-version-label">System Version</span>
    <strong><?= htmlspecialchars((string)$pdhVersion['version_code'], ENT_QUOTES, 'UTF-8') ?></strong>
    <span class="pdh-version-date"><?= htmlspecialchars((string)$pdhVersion['display_full_th'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<style>
.pdh-version-badge {
    position: fixed;
    right: 18px;
    bottom: 14px;
    z-index: 1100;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid rgba(148, 163, 184, .35);
    border-radius: 999px;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .10);
    color: #0f172a;
    font-size: 12px;
    font-weight: 700;
    backdrop-filter: blur(8px);
}
.pdh-version-label {
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: 11px;
}
.pdh-version-date {
    color: #2563eb;
}
@media (max-width: 991px) {
    .pdh-version-badge {
        right: 12px;
        bottom: 12px;
        gap: 6px;
        padding: 7px 10px;
        font-size: 11px;
    }
    .pdh-version-label {
        display: none;
    }
}
</style>

<script>
(function () {
    let activeRequests = 0;
    let hideTimer = null;
    const loaderId = 'pdhGlobalLoader';
    const textId = 'pdhLoaderText';

    function loader() {
        return document.getElementById(loaderId);
    }

    function setText(text) {
        const el = document.getElementById(textId);
        if (el) {
            el.textContent = text || 'กำลังโหลดข้อมูล...';
        }
    }

    function show(text) {
        activeRequests += 1;
        window.clearTimeout(hideTimer);
        setText(text);
        const el = loader();
        if (el) {
            el.classList.add('is-visible');
        }
    }

    function hide(force) {
        activeRequests = force ? 0 : Math.max(0, activeRequests - 1);
        if (activeRequests > 0) {
            return;
        }

        hideTimer = window.setTimeout(function () {
            const el = loader();
            if (el) {
                el.classList.remove('is-visible');
            }
        }, 180);
    }

    window.PDHLoader = { show, hide: function () { hide(false); }, forceHide: function () { hide(true); } };

    if (window.fetch) {
        const originalFetch = window.fetch.bind(window);
        window.fetch = function () {
            show('กำลังประมวลผล...');
            return originalFetch.apply(window, arguments).finally(function () {
                hide(false);
            });
        };
    }

    window.addEventListener('load', function () {
        hide(true);
    });

    window.addEventListener('beforeunload', function () {
        const el = loader();
        if (el) {
            setText('กำลังเปิดหน้า...');
            el.classList.add('is-visible');
        }
    });
})();
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
if (window.jQuery && window.PDHLoader) {
    $(document)
        .ajaxStart(function () {
            window.PDHLoader.show('กำลังประมวลผล...');
        })
        .ajaxStop(function () {
            window.PDHLoader.hide();
        })
        .ajaxError(function () {
            window.PDHLoader.hide();
        });
}
</script>

</body>
</html>

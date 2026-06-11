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

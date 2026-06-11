$(document).ready(function () {
    loadCeoSummary();

    function loadCeoSummary() {
        $.ajax({
            url: 'api/get_ceo_summary.php',
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    $('#opd_total').text(res.opd_total.toLocaleString());
                    $('#ipd_total').text(res.ipd_total.toLocaleString());
                } else {
                    Swal.fire('แจ้งเตือน', res.message, 'warning');
                }
            },
            error: function () {
                Swal.fire('ผิดพลาด', 'ไม่สามารถโหลดข้อมูล Dashboard ได้', 'error');
            }
        });
    }
});
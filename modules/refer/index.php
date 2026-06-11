<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

$currentMonth = (int)date('m');
$currentYear = (int)date('Y');
$currentFiscalYear = ($currentMonth >= 10) ? $currentYear + 1 : $currentYear;
$currentFiscalYearTH = $currentFiscalYear + 543;
$user_role = $_SESSION['role'] ?? 'Executive';
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; min-height: 142px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -8px; bottom: -20px; font-size: 80px; opacity: 0.16; }
    .kpi-title { font-size: 14px; opacity: 0.92; font-weight: 700; }
    .kpi-value { font-size: 31px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .chart-block, .table-card, .filter-card, .metric-strip { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; }
    .chart-canvas { position: relative; height: 300px; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #ef4444; padding-left: 12px; }
    .metric-item { border-left: 1px solid #e5e7eb; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 24px; font-weight: 800; }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    @media (max-width: 991px) {
        .content { margin-left: 0; padding: 18px; }
        .metric-item { border-left: 0; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px; }
        .metric-item:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
    }
</style>

<div class="content">
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-ambulance text-danger"></i> ศูนย์ส่งต่อผู้ป่วย (Refer Out Dashboard)</h4>
                <div class="text-secondary small">ติดตามการส่งต่อรายวัน รายเดือน รายไตรมาส แผนกต้นทาง โรงพยาบาลปลายทาง และสิทธิการรักษา</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-danger p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>REFERDB Live</span>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-bold small text-secondary">ปีงบประมาณ</label>
                <select id="fiscalYear" class="form-select form-select-sm">
                    <option value="<?= $currentFiscalYear ?>" selected>ปีงบประมาณ <?= $currentFiscalYearTH ?> (ปัจจุบัน)</option>
                    <option value="<?= $currentFiscalYear - 1 ?>">ปีงบประมาณ <?= $currentFiscalYearTH - 1 ?></option>
                    <option value="<?= $currentFiscalYear - 2 ?>">ปีงบประมาณ <?= $currentFiscalYearTH - 2 ?></option>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label fw-bold small text-secondary">หน่วยงานต้นทาง</label>
                <select id="stationFilter" class="form-select form-select-sm">
                    <option value="ALL">รวมทุกแผนก</option>
                    <option value="er">ER</option>
                    <option value="ward">WARD / IPD</option>
                    <option value="opd">OPD</option>
                </select>
            </div>
            <div class="col-lg-4">
                <button id="btnProcess" class="btn btn-danger btn-sm w-100 fw-bold py-2">
                    <i class="bi bi-lightning-charge-fill"></i> ประมวลผลข้อมูล
                </button>
            </div>
        </div>
    </div>

    <div class="section-title">สถานการณ์ Refer วันนี้</div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);" onclick="setStation('opd')">
                <div class="kpi-title">OPD Refer วันนี้</div>
                <div class="kpi-value" id="todayOpdRefer">0</div>
                <div class="kpi-sub">คลิกเพื่อกรอง OPD</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #10b981, #047857);" onclick="setStation('ward')">
                <div class="kpi-title">WARD Refer วันนี้</div>
                <div class="kpi-value" id="todayIpdRefer">0</div>
                <div class="kpi-sub">คลิกเพื่อกรอง WARD/IPD</div>
                <i class="bi bi-building-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #ef4444, #b91c1c);" onclick="setStation('er')">
                <div class="kpi-title">ER Refer วันนี้</div>
                <div class="kpi-value" id="todayErRefer">0</div>
                <div class="kpi-sub">คลิกเพื่อกรอง ER</div>
                <i class="bi bi-heart-pulse-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);" onclick="setStation('ALL')">
                <div class="kpi-title">รวม Refer วันนี้</div>
                <div class="kpi-value" id="todayTotalRefer">0</div>
                <div class="kpi-sub">ทุกหน่วยงานต้นทาง</div>
                <i class="bi bi-send-fill icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวมปีงบประมาณ <span id="fiscalLabel">-</span></div>
    <div class="metric-strip">
        <div class="row g-3 text-center">
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">รวมส่งต่อ</div>
                <div class="metric-value" id="kpiTotal">0</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">เฉลี่ยต่อเดือน</div>
                <div class="metric-value" id="kpiAvg">0</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label" id="kpiHospName">ปลายทางสูงสุด</div>
                <div class="metric-value" id="kpiHospVal">0</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label" id="kpiPtName">สิทธิสูงสุด</div>
                <div class="metric-value" id="kpiPtVal">0</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3" id="chartTitle"><i class="bi bi-bar-chart-fill text-danger"></i> แนวโน้มการส่งต่อรายเดือน</h6>
                <div class="chart-canvas"><canvas id="referMonthChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3-fill text-primary"></i> สัดส่วนแผนกต้นทางปีงบนี้</h6>
                <div class="chart-canvas"><canvas id="stationChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-grid-3x3-gap-fill text-success"></i> เปรียบเทียบรายไตรมาส</h6>
                <div class="chart-canvas"><canvas id="quarterChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-info"></i> สิทธิการรักษาที่ส่งต่อสูงสุด</h6>
                <div class="chart-canvas"><canvas id="pttypeBarChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="chart-block">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hospital-fill text-danger"></i> โรงพยาบาลปลายทางที่รับส่งต่อสูงสุด</h6>
        <div class="chart-canvas"><canvas id="hospitalChart"></canvas></div>
    </div>

    <div class="table-card">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-success"></i> รายละเอียดแยกตามโรงพยาบาลปลายทางและเดือน</h6>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle border mb-0" id="referTable">
                <thead>
                    <tr class="text-center align-middle">
                        <th rowspan="2">โรงพยาบาลปลายทาง</th>
                        <th rowspan="2" class="bg-danger text-white">รวม</th>
                        <th colspan="3" class="table-info">Q1</th>
                        <th colspan="3" class="table-warning">Q2</th>
                        <th colspan="3" class="table-success">Q3</th>
                        <th colspan="3" class="table-danger">Q4</th>
                    </tr>
                    <tr class="text-center text-muted" style="font-size: 13px; background: #f8fafc;">
                        <th>ต.ค.</th><th>พ.ย.</th><th>ธ.ค.</th>
                        <th>ม.ค.</th><th>ก.พ.</th><th>มี.ค.</th>
                        <th>เม.ย.</th><th>พ.ค.</th><th>มิ.ย.</th>
                        <th>ก.ค.</th><th>ส.ค.</th><th>ก.ย.</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let monthChartInst = null;
let stationChartInst = null;
let quarterChartInst = null;
let pttypeBarChartInst = null;
let hospitalChartInst = null;
let dataTableInst = null;

const visibleValueLabels = {
    id: 'visibleValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const { ctx, chartArea } = chart;
        const options = pluginOptions || {};
        const formatter = options.formatter || ((value) => numberText(value));

        ctx.save();
        ctx.font = options.font || '700 10px sans-serif';
        ctx.fillStyle = options.color || '#111827';
        ctx.strokeStyle = options.strokeColor || '#ffffff';
        ctx.lineWidth = 4;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;
            meta.data.forEach((element, index) => {
                const numericValue = Number(dataset.data[index] || 0);
                if (!Number.isFinite(numericValue) || numericValue === 0) return;
                let x = element.x;
                let y = element.y;
                let align = 'center';
                let baseline = 'bottom';

                if (chart.config.type === 'doughnut') {
                    const props = element.getProps(['x', 'y', 'startAngle', 'endAngle', 'innerRadius', 'outerRadius'], true);
                    const angle = (props.startAngle + props.endAngle) / 2;
                    const radius = (props.innerRadius + props.outerRadius) / 2;
                    x = props.x + Math.cos(angle) * radius;
                    y = props.y + Math.sin(angle) * radius;
                    baseline = 'middle';
                } else if (chart.options.indexAxis === 'y') {
                    x = Math.min(element.x + 8, chartArea.right - 8);
                    y = element.y;
                    align = 'left';
                    baseline = 'middle';
                } else {
                    y -= 8 + (datasetIndex * 11);
                }

                const label = formatter(numericValue, dataset, index);
                ctx.textAlign = align;
                ctx.textBaseline = baseline;
                ctx.strokeText(label, x, y);
                ctx.fillText(label, x, y);
            });
        });
        ctx.restore();
    }
};

Chart.register(visibleValueLabels);

$(document).ready(function() {
    fetchReferData();
    $('#stationFilter, #fiscalYear').change(fetchReferData);
    $('#btnProcess').click(fetchReferData);
});

function numberText(value, digits = 0) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    });
}

function thaiDate(dateText) {
    if (!dateText) return '-';
    const date = new Date(dateText + 'T00:00:00');
    return date.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function setStation(station) {
    $('#stationFilter').val(station).change();
}

function fetchReferData() {
    Swal.fire({
        title: 'กำลังเชื่อมโยง REFERDB...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: '../../api/get_refer_dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {
            fiscal_year: $('#fiscalYear').val(),
            station: $('#stationFilter').val()
        },
        success: function(res) {
            Swal.close();
            if (res.status !== 'success') {
                Swal.fire('ข้อความแจ้งเตือน', res.message || 'ไม่สามารถโหลดข้อมูล Refer ได้', 'warning');
                return;
            }

            renderKpis(res);
            renderMonthChart(res.chart_data);
            renderStationChart(res.station_breakdown);
            renderQuarterChart(res.quarter_data);
            renderPttypeBarChart(res.pttype_data);
            renderHospitalChart(res.hospital_chart);
            renderTable(res.table_data);
        },
        error: function() {
            Swal.close();
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อไฟล์ประมวลผลได้', 'error');
        }
    });
}

function renderKpis(res) {
    const today = res.today_stats || {};
    const totalToday = Number(today.opd || 0) + Number(today.ward || 0) + Number(today.er || 0);
    const chart = res.chart_data || {};
    const totalRefers = Object.values(chart).reduce((sum, value) => sum + Number(value || 0), 0);
    const avg = Math.round(totalRefers / 12);
    const range = res.fiscal_range || {};

    $('#todayOpdRefer').text(numberText(today.opd));
    $('#todayIpdRefer').text(numberText(today.ward));
    $('#todayErRefer').text(numberText(today.er));
    $('#todayTotalRefer').text(numberText(totalToday));
    $('#fiscalLabel').text(`${res.fiscal_year || '-'} (${thaiDate(range.start)} - ${thaiDate(range.end)})`);
    $('#kpiTotal').text(numberText(totalRefers));
    $('#kpiAvg').text(numberText(avg));

    if (res.table_data && res.table_data.length > 0) {
        $('#kpiHospName').text(res.table_data[0].hname || 'ปลายทางสูงสุด');
        $('#kpiHospVal').text(numberText(res.table_data[0].total));
    } else {
        $('#kpiHospName').text('ปลายทางสูงสุด');
        $('#kpiHospVal').text('0');
    }

    if (res.pttype_data && res.pttype_data.labels.length > 0) {
        $('#kpiPtName').text(res.pttype_data.labels[0] || 'สิทธิสูงสุด');
        $('#kpiPtVal').text(numberText(res.pttype_data.data[0]));
    } else {
        $('#kpiPtName').text('สิทธิสูงสุด');
        $('#kpiPtVal').text('0');
    }

    $('#chartTitle').html(`<i class="bi bi-bar-chart-fill text-danger"></i> แนวโน้มการส่งต่อ - ${$('#stationFilter option:selected').text()}`);
}

function monthValues(data) {
    return [data.oct, data.nov, data.dec, data.jan, data.feb, data.mar, data.apr, data.may, data.jun, data.jul, data.aug, data.sep].map(value => Number(value || 0));
}

function renderMonthChart(data) {
    const ctx = document.getElementById('referMonthChart');
    if (monthChartInst) monthChartInst.destroy();

    monthChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ต.ค.', 'พ.ย.', 'ธ.ค.', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.'],
            datasets: [{
                label: 'จำนวนส่งต่อ',
                data: monthValues(data || {}),
                backgroundColor: '#ef4444',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, visibleValueLabels: { color: '#0f172a' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderStationChart(data) {
    const ctx = document.getElementById('stationChart');
    if (stationChartInst) stationChartInst.destroy();

    stationChartInst = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['OPD', 'WARD', 'ER', 'อื่น ๆ'],
            datasets: [{
                data: [data.opd || 0, data.ward || 0, data.er || 0, data.other || 0],
                backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#64748b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' }, visibleValueLabels: { color: '#0f172a' } }
        }
    });
}

function renderQuarterChart(data) {
    const ctx = document.getElementById('quarterChart');
    if (quarterChartInst) quarterChartInst.destroy();

    quarterChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'จำนวนส่งต่อ',
                data: data.data || [],
                backgroundColor: ['#38bdf8', '#f59e0b', '#22c55e', '#ef4444'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, visibleValueLabels: { color: '#0f172a' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderPttypeBarChart(data) {
    const ctx = document.getElementById('pttypeBarChart');
    if (pttypeBarChartInst) pttypeBarChartInst.destroy();

    pttypeBarChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'จำนวน',
                data: data.data || [],
                backgroundColor: ['#2563eb', '#10b981', '#f97316', '#8b5cf6', '#ec4899', '#64748b'],
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, visibleValueLabels: { color: '#0f172a' } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

function renderHospitalChart(data) {
    const ctx = document.getElementById('hospitalChart');
    if (hospitalChartInst) hospitalChartInst.destroy();

    hospitalChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'จำนวน',
                data: data.data || [],
                backgroundColor: '#dc2626',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, visibleValueLabels: { color: '#0f172a' } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

function renderTable(data) {
    if (dataTableInst) dataTableInst.destroy();

    let html = '';
    if (!data || data.length === 0) {
        html = '<tr><td colspan="14" class="text-center text-muted py-3">ไม่พบประวัติข้อมูลส่งต่อ</td></tr>';
    } else {
        data.forEach(row => {
            html += `<tr>
                <td class="text-start fw-bold text-dark">${escapeHtml(row.hname || 'โรงพยาบาลไม่ระบุชื่อ')}</td>
                <td class="text-center fw-bold text-danger bg-danger bg-opacity-10">${numberText(row.total)}</td>
                <td class="text-center">${numberText(row.oct)}</td><td class="text-center">${numberText(row.nov)}</td><td class="text-center">${numberText(row.dec)}</td>
                <td class="text-center">${numberText(row.jan)}</td><td class="text-center">${numberText(row.feb)}</td><td class="text-center">${numberText(row.mar)}</td>
                <td class="text-center">${numberText(row.apr)}</td><td class="text-center">${numberText(row.may)}</td><td class="text-center">${numberText(row.jun)}</td>
                <td class="text-center">${numberText(row.jul)}</td><td class="text-center">${numberText(row.aug)}</td><td class="text-center">${numberText(row.sep)}</td>
            </tr>`;
        });
    }

    $('#referTable tbody').html(html);

    if (data && data.length > 0 && $.fn.DataTable) {
        dataTableInst = $('#referTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
            order: [[1, 'desc']],
            pageLength: 10,
            bLengthChange: false
        });
    }
}
</script>

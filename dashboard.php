<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/functions.php';

$user_role = $_SESSION['role'] ?? 'Executive';
?>

<?php include_once __DIR__ . '/layout/header.php'; ?>
<?php include_once __DIR__ . '/layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; min-height: 142px; transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -8px; bottom: -20px; font-size: 80px; opacity: 0.16; }
    .kpi-title { font-size: 14px; opacity: 0.92; font-weight: 700; }
    .kpi-value { font-size: 31px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .chart-block, .table-card, .filter-card { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; }
    .chart-canvas { position: relative; height: 300px; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #2563eb; padding-left: 12px; }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    .finance-hub { background: linear-gradient(135deg, #0f766e, #164e63); border-radius: 15px; padding: 22px; color: #fff; box-shadow: 0 8px 22px rgba(15, 118, 110, 0.16); margin-bottom: 24px; }
    .finance-hub-title { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
    .finance-hub-text { color: rgba(255, 255, 255, 0.82); font-size: 13px; }
    .finance-hub-metric { background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 12px; padding: 12px 14px; min-height: 76px; }
    .finance-hub-label { color: rgba(255, 255, 255, 0.72); font-size: 12px; font-weight: 700; }
    .finance-hub-value { font-size: 20px; font-weight: 800; line-height: 1.25; }
    .finance-hub .btn { border-radius: 10px; font-weight: 800; }
    @media (max-width: 991px) { .content { margin-left: 0; padding: 18px; } }
</style>

<div class="content">
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-cpu-fill text-primary"></i> แผงวิเคราะห์ข้อมูลบริการ (Intelligent Analytics)</h4>
                <div class="text-secondary small">วิเคราะห์ผู้ป่วยนอกตามช่วงเวลา เวร หน่วยบริการ สิทธิการรักษา และกลุ่มโรคสำคัญ</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-primary p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>HIMPRO Live</span>
            </div>
        </div>
    </div>

    <div class="finance-hub">
        <div class="row align-items-center g-3">
            <div class="col-xl-4">
                <div class="finance-hub-title"><i class="bi bi-bank2"></i> ระบบการเงินอัจฉริยะจากงบทดลอง</div>
                <div class="finance-hub-text">เชื่อมข้อมูล Trial Balance, Mapping บัญชี, KPI การเงิน และ HIS เพื่อสรุปสถานะสำหรับผู้บริหาร</div>
            </div>
            <div class="col-xl-5">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="finance-hub-metric">
                            <div class="finance-hub-label">งบทดลองล่าสุด</div>
                            <div class="finance-hub-value" id="financeTrialMonth">กำลังตรวจ...</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="finance-hub-metric">
                            <div class="finance-hub-label">จำนวนบัญชี</div>
                            <div class="finance-hub-value" id="financeTrialRows">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="finance-hub-metric">
                            <div class="finance-hub-label">ข้อมูลที่ต้องเติม</div>
                            <div class="finance-hub-value" id="financeIssueCount">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="d-grid gap-2">
                    <a class="btn btn-light text-success" href="modules/finance/index.php"><i class="bi bi-speedometer2"></i> เปิด Dashboard การเงิน</a>
                    <div class="btn-group" role="group" aria-label="Finance quick actions">
                        <a class="btn btn-outline-light" href="modules/finance/data_entry.php"><i class="bi bi-file-earmark-arrow-up"></i> นำเข้า</a>
                        <a class="btn btn-outline-light" href="modules/finance/governance.php"><i class="bi bi-diagram-3"></i> Mapping</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label fw-bold text-secondary small"><i class="bi bi-calendar3"></i> ช่วงวันที่</label>
                <div class="input-group input-group-sm">
                    <input type="date" id="startDate" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    <span class="input-group-text">ถึง</span>
                    <input type="date" id="endDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-secondary small"><i class="bi bi-clock-fill"></i> มิติเวลา</label>
                <select id="periodType" class="form-select form-select-sm">
                    <option value="daily">รายวัน</option>
                    <option value="weekly">รายสัปดาห์</option>
                    <option value="monthly" selected>รายเดือน</option>
                    <option value="quarterly">รายไตรมาส</option>
                    <option value="yearly">รายปี ย้อนหลัง 5 ปี</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-secondary small"><i class="bi bi-person-workspace"></i> เวร</label>
                <select id="shiftFilter" class="form-select form-select-sm">
                    <option value="ALL">ทุกเวร</option>
                    <option value="MORNING">เวรเช้า 08:00-15:59</option>
                    <option value="AFTERNOON">เวรบ่าย 16:00-23:59</option>
                    <option value="NIGHT">เวรดึก 00:00-07:59</option>
                </select>
            </div>
            <div class="col-lg-3">
                <button id="btnProcess" class="btn btn-primary btn-sm w-100 fw-bold py-2">
                    <i class="bi bi-lightning-charge-fill"></i> ประมวลผล
                </button>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวมบริการ <span id="rangeLabel">-</span></div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="kpi-title">ผู้รับบริการรวม</div>
                <div class="kpi-value" id="totalVisits">0</div>
                <div class="kpi-sub">จำนวน visit ในช่วงที่เลือก</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #10b981, #047857);">
                <div class="kpi-title">ผู้ป่วยไม่ซ้ำ HN</div>
                <div class="kpi-value" id="uniquePatients">0</div>
                <div class="kpi-sub">Distinct HN</div>
                <i class="bi bi-person-check-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);">
                <div class="kpi-title">เฉลี่ยต่อวัน</div>
                <div class="kpi-value" id="avgPerDay">0.0</div>
                <div class="kpi-sub">Visit / day</div>
                <i class="bi bi-bar-chart-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <div class="kpi-title">เวรที่ภาระงานสูงสุด</div>
                <div class="kpi-value" id="peakShift" style="font-size: 28px;">-</div>
                <div class="kpi-sub" id="newVisits">ผู้ป่วยใหม่ 0 ครั้ง</div>
                <i class="bi bi-clock-history icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3" id="chartTitle"><i class="bi bi-graph-up text-primary"></i> แนวโน้มผู้รับบริการ</h6>
                <div class="chart-canvas"><canvas id="analyticsTrendChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill text-warning"></i> ภาระงานแยกตามเวร</h6>
                <div class="chart-canvas"><canvas id="shiftPieChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hospital-fill text-success"></i> หน่วยบริการที่มีปริมาณสูงสุด</h6>
                <div class="chart-canvas"><canvas id="clinicChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-info"></i> สิทธิการรักษาสูงสุด</h6>
                <div class="chart-canvas"><canvas id="ptclassChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-virus2 text-danger"></i> 10 อันดับการวินิจฉัยโรคหลักสูงสุด</h6>
        <div class="table-responsive">
            <table class="table table-striped table-hover border mb-0" id="diagTable">
                <thead class="table-light">
                    <tr>
                        <th width="10%">อันดับ</th>
                        <th width="18%">รหัสโรค</th>
                        <th>ชื่อการวินิจฉัยโรค</th>
                        <th width="18%" class="text-end">จำนวน</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let trendChart = null;
let shiftChart = null;
let clinicChart = null;
let ptclassChart = null;

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
    fetchAnalytics();
    fetchFinanceSummary();
    $('#btnProcess').click(fetchAnalytics);
    $('#periodType, #shiftFilter').change(fetchAnalytics);
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

function fetchAnalytics() {
    Swal.fire({
        title: 'กำลังประมวลผลข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'api/get_dashboard_analytics.php',
        type: 'GET',
        dataType: 'json',
        data: {
            start_date: $('#startDate').val(),
            end_date: $('#endDate').val(),
            period_type: $('#periodType').val(),
            shift: $('#shiftFilter').val()
        },
        success: function(res) {
            Swal.close();
            if (res.status !== 'success') {
                Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถประมวลผลข้อมูลได้', 'error');
                return;
            }

            renderKpis(res);
            renderTrendChart(res.trend || {});
            renderShiftChart(res.shifts || {});
            renderClinicChart(res.clinic_chart || {});
            renderPtclassChart(res.ptclass_chart || {});
            renderDiagTable(res.top10_diag || []);
        },
        error: function() {
            Swal.close();
            Swal.fire('เชื่อมต่อล้มเหลว', 'ไม่สามารถประมวลผลรายงานอัจฉริยะได้', 'error');
        }
    });
}

function fetchFinanceSummary() {
    const now = new Date();
    const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

    $.ajax({
        url: 'api/get_finance_dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: { month },
        success: function(res) {
            if (res.status !== 'success') {
                renderFinanceSummary(null);
                return;
            }
            renderFinanceSummary(res.data_diagnostics || null);
        },
        error: function() {
            renderFinanceSummary(null);
        }
    });
}

function renderFinanceSummary(diagnostics) {
    const trial = diagnostics?.trial_balance || {};
    const issues = diagnostics?.issues || [];
    $('#financeTrialMonth').text(trial.ready ? trial.month : 'ยังไม่พบ');
    $('#financeTrialRows').text(trial.ready ? numberText(trial.row_count) : '-');
    $('#financeIssueCount').text(`${numberText(issues.length)} รายการ`);
}

function renderKpis(res) {
    const kpi = res.kpi || {};
    const range = res.range || {};
    const typeText = $('#periodType option:selected').text();

    $('#rangeLabel').text(`(${thaiDate(range.start)} - ${thaiDate(range.end)})`);
    $('#chartTitle').html(`<i class="bi bi-graph-up text-primary"></i> แนวโน้มผู้รับบริการ - ${typeText}`);
    $('#totalVisits').text(numberText(kpi.total_visits));
    $('#uniquePatients').text(numberText(kpi.unique_patients));
    $('#avgPerDay').text(numberText(kpi.avg_per_day, 1));
    $('#peakShift').text(kpi.peak_shift || '-');
    $('#newVisits').text(`ผู้ป่วยใหม่ ${numberText(kpi.new_visits)} ครั้ง`);
}

function renderTrendChart(trendData) {
    const ctx = document.getElementById('analyticsTrendChart');
    if (trendChart) trendChart.destroy();

    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.labels || [],
            datasets: [{
                label: 'จำนวนผู้มารับบริการ',
                data: trendData.values || [],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.10)',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, visibleValueLabels: { color: '#0f172a' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderShiftChart(shiftData) {
    const ctx = document.getElementById('shiftPieChart');
    if (shiftChart) shiftChart.destroy();

    shiftChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['เวรเช้า', 'เวรบ่าย', 'เวรดึก'],
            datasets: [{
                data: [shiftData.morning || 0, shiftData.afternoon || 0, shiftData.night || 0],
                backgroundColor: ['#3b82f6', '#f59e0b', '#dc2626'],
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

function renderClinicChart(data) {
    const ctx = document.getElementById('clinicChart');
    if (clinicChart) clinicChart.destroy();

    clinicChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{ label: 'จำนวน', data: data.data || [], backgroundColor: '#10b981', borderRadius: 6 }]
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

function renderPtclassChart(data) {
    const ctx = document.getElementById('ptclassChart');
    if (ptclassChart) ptclassChart.destroy();

    ptclassChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{ label: 'จำนวน', data: data.data || [], backgroundColor: ['#2563eb', '#10b981', '#f97316', '#8b5cf6', '#ec4899', '#64748b', '#14b8a6', '#f59e0b'], borderRadius: 6 }]
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

function renderDiagTable(diagData) {
    let html = '';
    if (!diagData || diagData.length === 0) {
        html = '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูลสถิติโรคในช่วงเวลานี้</td></tr>';
    } else {
        diagData.forEach((row, index) => {
            html += `<tr>
                <td class="fw-bold text-secondary">${index + 1}</td>
                <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle">${escapeHtml(row.pdx)}</span></td>
                <td>${escapeHtml(row.diag_name)}</td>
                <td class="text-end fw-bold text-dark">${numberText(row.total)}</td>
            </tr>`;
        });
    }
    $('#diagTable tbody').html(html);
}
</script>

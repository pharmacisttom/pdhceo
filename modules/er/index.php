<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

$user_role = $_SESSION['role'] ?? 'Executive';
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; min-height: 142px; transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -8px; bottom: -20px; font-size: 80px; opacity: 0.16; }
    .kpi-title { font-size: 14px; opacity: 0.92; font-weight: 700; }
    .kpi-value { font-size: 31px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .chart-block, .table-card, .metric-strip { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; }
    .chart-canvas { position: relative; height: 300px; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #dc2626; padding-left: 12px; }
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
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-heart-pulse-fill text-danger"></i> งานอุบัติเหตุและฉุกเฉิน (ER Realtime Dashboard)</h4>
                <div class="text-secondary small">ติดตามปริมาณผู้ป่วยฉุกเฉิน การส่งต่อ การ Admit ช่วงเวลาหนาแน่น และกลุ่มโรคสำคัญ</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-danger p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>HIS Live</span>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวม ER ปีงบประมาณ <span id="fiscalYear">-</span></div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
                <div class="kpi-title">ER วันนี้</div>
                <div class="kpi-value" id="erToday">0</div>
                <div class="kpi-sub">จำนวนครั้งรับบริการวันนี้</div>
                <i class="bi bi-activity icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0ea5e9, #0369a1);">
                <div class="kpi-title">ER สะสมปีงบนี้</div>
                <div class="kpi-value" id="erFy">0</div>
                <div class="kpi-sub" id="rangeText">ปีงบประมาณปัจจุบัน</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);">
                <div class="kpi-title">ER Refer</div>
                <div class="kpi-value" id="referFy">0</div>
                <div class="kpi-sub" id="referSub">วันนี้ 0 ราย</div>
                <i class="bi bi-ambulance icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #6366f1, #4338ca);">
                <div class="kpi-title">Admit จาก ER</div>
                <div class="kpi-value" id="admitFy">0</div>
                <div class="kpi-sub" id="admitSub">วันนี้ 0 ราย</div>
                <i class="bi bi-hospital-fill icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="metric-strip">
        <div class="row g-3 text-center">
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">ผู้ป่วยไม่ซ้ำ HN</div>
                <div class="metric-value" id="uniquePatients">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">ผู้ป่วยใหม่</div>
                <div class="metric-value" id="newCases">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">เฉลี่ยต่อวัน</div>
                <div class="metric-value" id="avgPerDay">0.0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">เสียชีวิตที่ ER</div>
                <div class="metric-value text-danger" id="deathFy">0</div>
                <div class="small text-muted" id="deathToday">วันนี้ 0 ราย</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">Refer / Admit Rate</div>
                <div class="metric-value" id="rateSummary">0.00%</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-danger"></i> แนวโน้ม ER, Refer และ Admit รายเดือน</h6>
                <div class="chart-canvas"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-fill text-primary"></i> ภาระงานแยกตามเวร</h6>
                <div class="chart-canvas"><canvas id="shiftChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-warning"></i> ความหนาแน่น ER วันนี้รายชั่วโมง</h6>
                <div class="chart-canvas"><canvas id="hourlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-success"></i> สัดส่วนสิทธิการรักษา ER</h6>
                <div class="chart-canvas"><canvas id="payerChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="chart-block">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clipboard2-pulse-fill text-danger"></i> ผลการรักษา ER ปีงบนี้</h6>
        <div class="chart-canvas"><canvas id="outcomeChart"></canvas></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-virus2 text-danger"></i> 10 อันดับการวินิจฉัยหลัก ER ปีงบนี้</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="diagTable">
                        <thead class="table-light">
                            <tr>
                                <th>ICD-10</th>
                                <th>ชื่อโรค</th>
                                <th class="text-end">จำนวน</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="3" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-list text-primary"></i> สรุปผู้ป่วย ER วันนี้แยกตามสิทธิการรักษา</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="todayPayerTable">
                        <thead class="table-light">
                            <tr>
                                <th>สิทธิการรักษา</th>
                                <th class="text-end">จำนวนครั้ง</th>
                                <th class="text-end">จำนวนคน</th>
                                <th style="width: 30%">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-heartbreak-fill text-danger"></i> โรค/สาเหตุหลักของผู้เสียชีวิตที่ ER ปีงบนี้</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="deathDiagTable">
                <thead class="table-light">
                    <tr>
                        <th>ICD-10</th>
                        <th>โรค/สาเหตุ</th>
                        <th class="text-end">จำนวน</th>
                        <th style="width: 30%">สัดส่วน</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
            </table>
        </div>
        <div class="small text-muted mt-2">หมายเหตุ: อ้างอิง `opd.opd.result` join กับ `hos.codeinhos` โดย `RST5 = ตายที่ห้องฉุกเฉิน`</div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let monthlyChart = null;
let shiftChart = null;
let hourlyChart = null;
let payerChart = null;
let outcomeChart = null;

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
                const rawValue = dataset.data[index];
                const numericValue = Number(rawValue || 0);
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
    loadErDashboard();
    setInterval(loadErDashboard, 300000);
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

function loadErDashboard() {
    $.ajax({
        url: '../../api/get_er_dashboard.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.status !== 'success') {
                Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถโหลดข้อมูล ER ได้', 'error');
                return;
            }

            renderKpis(res);
            renderMonthlyChart(res.charts.monthly);
            renderShiftChart(res.charts.shift);
            renderHourlyChart(res.charts.hourly_today);
            renderPayerChart(res.charts.payer);
            renderOutcomeChart(res.charts.outcome || {});
            renderDiagTable(res.top_diagnosis);
            renderDeathDiagTable(res.death_diagnosis);
            renderTodayPayerTable(res.today_payer_summary || res.recent_today);
        },
        error: function() {
            Swal.fire('เชื่อมต่อล้มเหลว', 'ไม่สามารถติดต่อ API ข้อมูล ER ได้', 'error');
        }
    });
}

function renderKpis(res) {
    const kpi = res.kpi || {};
    const range = res.fiscal_range || {};

    $('#fiscalYear').text(`${res.fiscal_year || '-'} (${thaiDate(range.start)} - ${thaiDate(range.end)})`);
    $('#rangeText').text(`${thaiDate(range.start)} - ${thaiDate(range.end)}`);
    $('#erToday').text(numberText(kpi.er_today));
    $('#erFy').text(numberText(kpi.er_fy));
    $('#referFy').text(numberText(kpi.refer_fy));
    $('#referSub').text(`วันนี้ ${numberText(kpi.refer_today)} ราย | ${numberText(kpi.refer_rate, 2)}%`);
    $('#admitFy').text(numberText(kpi.admit_fy));
    $('#admitSub').text(`วันนี้ ${numberText(kpi.admit_today)} ราย | ${numberText(kpi.admit_rate, 2)}%`);
    $('#uniquePatients').text(numberText(kpi.unique_patients));
    $('#newCases').text(numberText(kpi.new_cases));
    $('#avgPerDay').text(numberText(kpi.avg_per_day, 1));
    $('#deathFy').text(numberText(kpi.death_fy));
    $('#deathToday').text(`${kpi.death_result_name || 'ตายที่ห้องฉุกเฉิน'}: วันนี้ ${numberText(kpi.death_today)} ราย | ${numberText(kpi.death_rate, 2)}%`);
    $('#rateSummary').text(`${numberText(kpi.refer_rate, 2)} / ${numberText(kpi.admit_rate, 2)}%`);
}

function renderMonthlyChart(data) {
    const ctx = document.getElementById('monthlyChart');
    if (monthlyChart) monthlyChart.destroy();

    monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'ER Visit', data: data.er || [], borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.10)', borderWidth: 3, fill: true, tension: .35 },
                { label: 'Refer', data: data.refer || [], borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.10)', borderWidth: 3, fill: true, tension: .35 },
                { label: 'Admit', data: data.admit || [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.10)', borderWidth: 3, fill: true, tension: .35 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, visibleValueLabels: { color: '#0f172a' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderShiftChart(data) {
    const ctx = document.getElementById('shiftChart');
    if (shiftChart) shiftChart.destroy();

    shiftChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [{ data: data.data || [], backgroundColor: ['#0ea5e9', '#f97316', '#6366f1'], borderWidth: 0 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' }, visibleValueLabels: { color: '#0f172a' } }
        }
    });
}

function renderHourlyChart(data) {
    const ctx = document.getElementById('hourlyChart');
    if (hourlyChart) hourlyChart.destroy();

    hourlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{ label: 'ER วันนี้', data: data.data || [], backgroundColor: '#ef4444', borderRadius: 5 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, visibleValueLabels: { color: '#0f172a' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderPayerChart(data) {
    const ctx = document.getElementById('payerChart');
    if (payerChart) payerChart.destroy();

    payerChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{ label: 'จำนวนครั้ง', data: data.data || [], backgroundColor: ['#2563eb', '#10b981', '#f97316', '#ef4444', '#8b5cf6', '#14b8a6', '#64748b', '#f59e0b'], borderRadius: 6 }]
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

function renderOutcomeChart(data) {
    const ctx = document.getElementById('outcomeChart');
    if (outcomeChart) outcomeChart.destroy();

    outcomeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'จำนวน',
                data: data.data || [],
                backgroundColor: ['#10b981', '#6366f1', '#f97316', '#ef4444', '#64748b', '#8b5cf6', '#14b8a6', '#f59e0b'],
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

function renderDiagTable(rows) {
    if (!rows || rows.length === 0) {
        $('#diagTable tbody').html('<tr><td colspan="3" class="text-center text-muted">ไม่พบข้อมูลวินิจฉัย</td></tr>');
        return;
    }

    let html = '';
    rows.forEach(row => {
        html += `<tr>
            <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle">${escapeHtml(row.code)}</span></td>
            <td>${escapeHtml(row.name)}</td>
            <td class="text-end fw-bold">${numberText(row.total)}</td>
        </tr>`;
    });
    $('#diagTable tbody').html(html);
}

function renderTodayPayerTable(rows) {
    if (!rows || rows.length === 0) {
        $('#todayPayerTable tbody').html('<tr><td colspan="4" class="text-center text-muted">ยังไม่พบข้อมูลผู้ป่วย ER วันนี้</td></tr>');
        return;
    }

    let html = '';
    const max = Math.max(...rows.map(row => Number(row.total_visits || 0)), 1);
    rows.forEach(row => {
        const visits = Number(row.total_visits || 0);
        const patients = Number(row.total_patients || 0);
        const percent = Math.round((visits / max) * 100);
        html += `<tr>
            <td class="fw-bold text-dark">${escapeHtml(row.ptclass_name)}</td>
            <td class="text-end fw-bold text-danger">${numberText(visits)}</td>
            <td class="text-end fw-bold">${numberText(patients)}</td>
            <td>
                <div class="progress" style="height: 9px;">
                    <div class="progress-bar bg-danger" style="width: ${percent}%"></div>
                </div>
            </td>
        </tr>`;
    });
    $('#todayPayerTable tbody').html(html);
}

function renderDeathDiagTable(rows) {
    if (!rows || rows.length === 0) {
        $('#deathDiagTable tbody').html('<tr><td colspan="4" class="text-center text-muted">ยังไม่พบข้อมูลผู้เสียชีวิตที่ ER ในปีงบนี้</td></tr>');
        return;
    }

    const max = Math.max(...rows.map(row => Number(row.total || 0)), 1);
    let html = '';
    rows.forEach(row => {
        const total = Number(row.total || 0);
        const percent = Math.round((total / max) * 100);
        html += `<tr>
            <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle">${escapeHtml(row.code)}</span></td>
            <td>${escapeHtml(row.name)}</td>
            <td class="text-end fw-bold text-danger">${numberText(total)}</td>
            <td>
                <div class="progress" style="height: 9px;">
                    <div class="progress-bar bg-danger" style="width: ${percent}%"></div>
                </div>
            </td>
        </tr>`;
    });
    $('#deathDiagTable tbody').html(html);
}
</script>

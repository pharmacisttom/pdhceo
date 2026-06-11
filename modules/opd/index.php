<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

$user_role = $_SESSION['role'] ?? 'Executive';
$today = new DateTimeImmutable('today');
$year = (int)$today->format('Y');
$month = (int)$today->format('n');
$fiscalStart = $month >= 10 ? sprintf('%d-10-01', $year) : sprintf('%d-10-01', $year - 1);
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; min-height: 138px; transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -10px; bottom: -22px; font-size: 78px; opacity: 0.16; }
    .kpi-title { font-size: 13px; opacity: 0.94; font-weight: 700; }
    .kpi-value { font-size: 30px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .chart-block, .table-card, .filter-card, .metric-strip { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; }
    .chart-canvas { position: relative; height: 310px; }
    .chart-canvas.tall { height: 370px; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #2563eb; padding-left: 12px; }
    .metric-item { border-left: 1px solid #e5e7eb; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 24px; font-weight: 800; }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    .progress { background: #e5e7eb; }
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
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-people-fill text-primary"></i> งานผู้ป่วยนอก (OPD Performance Dashboard)</h4>
                <div class="text-secondary small">ติดตามปริมาณบริการ คลินิก สิทธิการรักษา ช่วงเวลาหนาแน่น ผลการรักษา และโรคสำคัญแบบ HIS Live</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-primary p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>HIS Live</span>
            </div>
        </div>
    </div>

    <div class="filter-card">
        <div class="row align-items-end g-3">
            <div class="col-lg-5">
                <div class="fw-bold text-dark"><i class="bi bi-calendar-range text-primary"></i> ช่วงข้อมูล OPD</div>
                <div class="small text-muted" id="periodText">ปีงบประมาณปัจจุบัน</div>
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label small text-muted mb-1">เริ่มวันที่</label>
                <input type="date" class="form-control" id="startDate" value="<?= htmlspecialchars($fiscalStart, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label small text-muted mb-1">ถึงวันที่</label>
                <input type="date" class="form-control" id="endDate" value="<?= htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-lg-1 col-md-4">
                <button class="btn btn-primary w-100" id="btnLoad" title="โหลดข้อมูล"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวมตัวชี้วัด OPD</div>
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="kpi-title">ผู้รับบริการรวม</div>
                <div class="kpi-value" id="totalVisits">0</div>
                <div class="kpi-sub">ครั้งรับบริการ</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0f766e, #0d9488);">
                <div class="kpi-title">ผู้ป่วยไม่ซ้ำ HN</div>
                <div class="kpi-value" id="uniquePatients">0</div>
                <div class="kpi-sub">คน</div>
                <i class="bi bi-person-badge icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);">
                <div class="kpi-title">Visit แรก</div>
                <div class="kpi-value" id="firstVisits">0</div>
                <div class="kpi-sub">frequency = 1</div>
                <i class="bi bi-person-plus-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                <div class="kpi-title">เฉลี่ยต่อวัน</div>
                <div class="kpi-value" id="avgPerDay">0</div>
                <div class="kpi-sub">ครั้ง/วัน</div>
                <i class="bi bi-graph-up-arrow icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #dc2626, #991b1b);">
                <div class="kpi-title">OPD ที่เข้า ER</div>
                <div class="kpi-value" id="erVisits">0</div>
                <div class="kpi-sub">clinic 130</div>
                <i class="bi bi-heart-pulse-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #475569, #334155);">
                <div class="kpi-title">Refer จาก OPD</div>
                <div class="kpi-value" id="referVisits">0</div>
                <div class="kpi-sub" id="referRate">0.00%</div>
                <i class="bi bi-ambulance icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="metric-strip">
        <div class="row g-3 text-center">
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">จำนวนคลินิกที่มีบริการ</div>
                <div class="metric-value" id="activeClinics">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">จำนวนสิทธิที่ใช้บริการ</div>
                <div class="metric-value" id="activePtclasses">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">คลินิกที่มารับบริการสูงสุด</div>
                <div class="metric-value fs-5" id="topClinic">-</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">สิทธิที่ใช้บริการสูงสุด</div>
                <div class="metric-value fs-5" id="topPtclass">-</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">จำนวนวันในช่วงที่เลือก</div>
                <div class="metric-value" id="rangeDays">0</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> แนวโน้มผู้รับบริการรายวัน</h6>
                <div class="chart-canvas"><canvas id="dailyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-fill text-info"></i> ภาระงานแยกตามเวร</h6>
                <div class="chart-canvas"><canvas id="shiftChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hospital-fill text-primary"></i> คลินิก/แผนกที่ให้บริการสูงสุด</h6>
                <div class="chart-canvas tall"><canvas id="clinicChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-success"></i> สิทธิการรักษาที่ใช้บริการสูงสุด</h6>
                <div class="chart-canvas"><canvas id="ptclassChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-warning"></i> ความหนาแน่นตามชั่วโมง</h6>
                <div class="chart-canvas"><canvas id="hourlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clipboard2-check-fill text-secondary"></i> ผลการรักษา OPD</h6>
                <div class="chart-canvas"><canvas id="outcomeChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-virus2 text-danger"></i> 15 อันดับการวินิจฉัยหลัก OPD</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="diagTable">
                        <thead class="table-light">
                            <tr>
                                <th>ICD-10</th>
                                <th>ชื่อโรค</th>
                                <th class="text-end">ครั้ง</th>
                                <th class="text-end">HN ไม่ซ้ำ</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle-fill text-warning"></i> ประเภท AE/ความเร่งด่วน</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="aeTable">
                        <thead class="table-light">
                            <tr>
                                <th>ประเภท</th>
                                <th class="text-end">จำนวน</th>
                                <th style="width: 34%">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="3" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-primary"></i> รายละเอียดแยกตามคลินิก</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="clinicTable">
                        <thead class="table-light">
                            <tr>
                                <th>คลินิก/แผนก</th>
                                <th class="text-end">ครั้ง</th>
                                <th class="text-end">HN ไม่ซ้ำ</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="3" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-success"></i> รายละเอียดแยกตามสิทธิการรักษา</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="ptclassTable">
                        <thead class="table-light">
                            <tr>
                                <th>สิทธิการรักษา</th>
                                <th class="text-end">ครั้ง</th>
                                <th class="text-end">HN ไม่ซ้ำ</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="3" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
let dailyChart = null;
let shiftChart = null;
let clinicChart = null;
let ptclassChart = null;
let hourlyChart = null;
let outcomeChart = null;

const visibleValueLabels = {
    id: 'visibleValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const { ctx, chartArea } = chart;
        const options = pluginOptions || {};
        const formatter = options.formatter || ((value) => numberText(value));

        ctx.save();
        ctx.font = options.font || '700 11px Sarabun, sans-serif';
        ctx.fillStyle = options.color || '#0f172a';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;
            meta.data.forEach((element, index) => {
                const value = Number(dataset.data[index] || 0);
                if (!value && options.hideZero !== false) return;
                const props = element.tooltipPosition();
                const y = Math.max(chartArea.top + 12, props.y - 6);
                ctx.fillText(formatter(value), props.x, y);
            });
        });
        ctx.restore();
    }
};
Chart.register(visibleValueLabels);

document.addEventListener('DOMContentLoaded', () => {
    loadOPD();
    document.getElementById('btnLoad').addEventListener('click', loadOPD);
});

async function loadOPD() {
    const params = new URLSearchParams({
        start_date: document.getElementById('startDate').value,
        end_date: document.getElementById('endDate').value
    });

    Swal.fire({
        title: 'กำลังดึงข้อมูล OPD จาก HIS...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch(`../../api/get_opd_dashboard.php?${params.toString()}`, { credentials: 'same-origin' });
        const data = await response.json();
        Swal.close();

        if (data.status !== 'success') {
            Swal.fire('แจ้งเตือน', data.message || 'ไม่สามารถโหลดข้อมูลได้', 'warning');
            return;
        }

        renderOPD(data);
    } catch (error) {
        Swal.close();
        Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
    }
}

function renderOPD(data) {
    const kpi = data.kpi || {};
    const range = data.range || {};
    const clinic = data.clinic || [];
    const ptclass = data.ptclass || [];

    setText('periodText', `ช่วงวันที่ ${range.label || '-'} (${numberText(range.days || 0)} วัน)`);
    setText('totalVisits', numberText(kpi.total_visits));
    setText('uniquePatients', numberText(kpi.unique_patients));
    setText('firstVisits', numberText(kpi.first_visits));
    setText('avgPerDay', numberText(kpi.avg_per_day));
    setText('erVisits', numberText(kpi.er_visits));
    setText('referVisits', numberText(kpi.refer_visits));
    setText('referRate', `${numberText(kpi.refer_rate)}% ของ OPD`);
    setText('activeClinics', numberText(kpi.active_clinics));
    setText('activePtclasses', numberText(kpi.active_ptclasses));
    setText('topClinic', clinic[0]?.name || '-');
    setText('topPtclass', ptclass[0]?.name || '-');
    setText('rangeDays', numberText(range.days));

    renderDailyChart(data.charts?.daily || {});
    renderShiftChart(data.charts?.shift || {});
    renderClinicChart(clinic);
    renderPtclassChart(ptclass);
    renderHourlyChart(data.charts?.hourly || {});
    renderOutcomeChart(data.charts?.outcomes || []);
    renderDiagnosisTable(data.diagnosis || []);
    renderAeTable(data.charts?.ae || [], kpi.total_visits || 0);
    renderSimpleTable('clinicTable', clinic, ['name', 'total', 'patients']);
    renderSimpleTable('ptclassTable', ptclass, ['name', 'total', 'patients']);
}

function renderDailyChart(daily) {
    dailyChart = replaceChart(dailyChart, 'dailyChart', {
        type: 'line',
        data: {
            labels: daily.labels || [],
            datasets: [
                {
                    label: 'ครั้งรับบริการ',
                    data: daily.visits || [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.14)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                },
                {
                    label: 'HN ไม่ซ้ำ',
                    data: daily.patients || [],
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.10)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                }
            ]
        },
        options: chartOptions({ labelColor: '#334155', maxTicksLimit: 10 })
    });
}

function renderShiftChart(shift) {
    shiftChart = replaceChart(shiftChart, 'shiftChart', {
        type: 'doughnut',
        data: {
            labels: shift.labels || [],
            datasets: [{
                data: shift.values || [],
                backgroundColor: ['#2563eb', '#f97316', '#64748b'],
                borderWidth: 0
            }]
        },
        options: doughnutOptions()
    });
}

function renderClinicChart(rows) {
    const sorted = rows.slice().reverse();
    clinicChart = replaceChart(clinicChart, 'clinicChart', {
        type: 'bar',
        data: {
            labels: sorted.map(row => row.name),
            datasets: [{
                label: 'ครั้งรับบริการ',
                data: sorted.map(row => row.total),
                backgroundColor: '#2563eb',
                borderRadius: 8
            }]
        },
        options: chartOptions({ indexAxis: 'y', labelColor: '#111827' })
    });
}

function renderPtclassChart(rows) {
    ptclassChart = replaceChart(ptclassChart, 'ptclassChart', {
        type: 'bar',
        data: {
            labels: rows.slice(0, 8).map(row => row.name),
            datasets: [{
                label: 'ครั้งรับบริการ',
                data: rows.slice(0, 8).map(row => row.total),
                backgroundColor: ['#0f766e', '#2563eb', '#f97316', '#7c3aed', '#dc2626', '#64748b', '#0891b2', '#ca8a04'],
                borderRadius: 8
            }]
        },
        options: chartOptions({ labelColor: '#334155', maxTicksLimit: 8 })
    });
}

function renderHourlyChart(hourly) {
    hourlyChart = replaceChart(hourlyChart, 'hourlyChart', {
        type: 'bar',
        data: {
            labels: hourly.labels || [],
            datasets: [{
                label: 'ครั้งรับบริการ',
                data: hourly.values || [],
                backgroundColor: '#f97316',
                borderRadius: 7
            }]
        },
        options: chartOptions({ labelColor: '#334155', maxTicksLimit: 12 })
    });
}

function renderOutcomeChart(rows) {
    outcomeChart = replaceChart(outcomeChart, 'outcomeChart', {
        type: 'bar',
        data: {
            labels: rows.map(row => row.name),
            datasets: [{
                label: 'จำนวน',
                data: rows.map(row => row.total),
                backgroundColor: '#475569',
                borderRadius: 8
            }]
        },
        options: chartOptions({ indexAxis: 'y', labelColor: '#111827' })
    });
}

function renderDiagnosisTable(rows) {
    const tbody = document.querySelector('#diagTable tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => `
        <tr>
            <td class="fw-bold text-primary">${escapeHtml(row.diag)}</td>
            <td>${escapeHtml(row.name)}</td>
            <td class="text-end fw-bold">${numberText(row.total)}</td>
            <td class="text-end">${numberText(row.patients)}</td>
        </tr>
    `).join('');
}

function renderAeTable(rows, totalVisits) {
    const tbody = document.querySelector('#aeTable tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => {
        const pct = totalVisits > 0 ? (Number(row.total || 0) / totalVisits) * 100 : 0;
        return `
            <tr>
                <td>${escapeHtml(row.name)}</td>
                <td class="text-end fw-bold">${numberText(row.total)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: ${Math.min(100, pct)}%"></div>
                        </div>
                        <span class="small text-muted">${pct.toFixed(1)}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderSimpleTable(tableId, rows, columns) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="${columns.length}" class="text-center text-muted">ไม่พบข้อมูล</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(row => `
        <tr>
            <td>${escapeHtml(row[columns[0]])}</td>
            <td class="text-end fw-bold">${numberText(row[columns[1]])}</td>
            <td class="text-end">${numberText(row[columns[2]])}</td>
        </tr>
    `).join('');
}

function replaceChart(currentChart, canvasId, config) {
    if (currentChart) currentChart.destroy();
    const ctx = document.getElementById(canvasId);
    return new Chart(ctx, config);
}

function chartOptions(extra = {}) {
    return {
        indexAxis: extra.indexAxis || 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'bottom', labels: { font: { family: 'Sarabun' } } },
            tooltip: { callbacks: { label: context => `${context.dataset.label || ''}: ${numberText(context.raw)}` } },
            visibleValueLabels: { color: extra.labelColor || '#0f172a', hideZero: true }
        },
        scales: {
            x: { ticks: { font: { family: 'Sarabun' }, maxRotation: 0, autoSkip: true, maxTicksLimit: extra.maxTicksLimit || 10 } },
            y: { beginAtZero: true, ticks: { font: { family: 'Sarabun' }, callback: value => numberText(value) } }
        }
    };
}

function doughnutOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '56%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { family: 'Sarabun' } } },
            tooltip: { callbacks: { label: context => `${context.label}: ${numberText(context.raw)}` } },
            visibleValueLabels: { color: '#111827', hideZero: true }
        }
    };
}

function setText(id, text) {
    document.getElementById(id).textContent = text ?? '-';
}

function numberText(value) {
    return Number(value || 0).toLocaleString('th-TH', { maximumFractionDigits: 1 });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}
</script>

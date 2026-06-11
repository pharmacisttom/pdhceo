<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

$userRole = $_SESSION['role'] ?? 'Executive';
?>
<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef6ff 100%); min-height: 100vh; }
    .hero-card, .panel-card, .table-card, .metric-strip { background: rgba(255,255,255,.96); border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); border: 1px solid rgba(148, 163, 184, 0.14); }
    .hero-card { padding: 24px; margin-bottom: 24px; background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%); color: #fff; overflow: hidden; position: relative; }
    .hero-glow { position: absolute; inset: auto -30px -50px auto; width: 220px; height: 220px; border-radius: 999px; background: rgba(255,255,255,.10); filter: blur(6px); }
    .kpi-card { border: 0; border-radius: 18px; overflow: hidden; min-height: 148px; color: #fff; position: relative; transition: transform .2s, box-shadow .2s; }
    .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -6px; bottom: -18px; font-size: 82px; opacity: .16; }
    .kpi-title { font-size: 13px; font-weight: 700; opacity: .92; }
    .kpi-value { font-size: 32px; font-weight: 800; line-height: 1.1; margin: 10px 0 4px; }
    .kpi-sub { font-size: 12px; opacity: .86; }
    .metric-strip { padding: 18px 22px; margin-bottom: 24px; }
    .metric-item { border-left: 1px solid #e2e8f0; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 25px; font-weight: 800; }
    .panel-card, .table-card { padding: 22px; margin-bottom: 24px; }
    .section-title { font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 14px; }
    .chart-wrap { position: relative; height: 320px; }
    .pill-note { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 12px; font-weight: 700; }
    .source-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 6px 10px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
    .mini-progress { height: 8px; }
    @media (max-width: 991px) {
        .content { margin-left: 0; padding: 18px; }
        .metric-item { border-left: 0; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 12px; }
        .metric-item:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
    }
</style>

<div class="content">
    <div class="hero-card">
        <div class="hero-glow"></div>
        <div class="row align-items-center g-3 position-relative">
            <div class="col-lg-8">
                <div class="small fw-bold text-uppercase opacity-75 mb-2">Executive Telemedicine Command Center</div>
                <h4 class="fw-bold mb-2"><i class="bi bi-camera-video-fill"></i> Telemed Dashboard สำหรับผู้บริหาร</h4>
                <div class="opacity-75">ติดตามปริมาณบริการ Telemed, ความคืบหน้าการดูแลผู้ป่วย, การจัดส่งยา และคลินิกที่มีภาระงานสูงจากข้อมูลจริงของ HIS และระบบ PDH Telemed</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="pill-note mb-2"><i class="bi bi-person-badge"></i> สิทธิ์: <?= e((string)$userRole) ?></div>
                <div class="pill-note"><i class="bi bi-database-check"></i> HIS + PDHTAWAN Live</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0284c7, #1d4ed8);">
                <div class="kpi-title">Telemed วันนี้</div>
                <div class="kpi-value" id="visitsToday">0</div>
                <div class="kpi-sub" id="patientsTodaySub">ผู้ป่วย 0 คน</div>
                <i class="bi bi-broadcast icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0f766e, #14b8a6);">
                <div class="kpi-title">สะสมปีงบประมาณ</div>
                <div class="kpi-value" id="visitsFy">0</div>
                <div class="kpi-sub" id="fiscalRangeText">ปีงบประมาณปัจจุบัน</div>
                <i class="bi bi-calendar2-week-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #ea580c, #f97316);">
                <div class="kpi-title">ผู้ป่วยไม่ซ้ำ HN ปีงบนี้</div>
                <div class="kpi-value" id="patientsFy">0</div>
                <div class="kpi-sub" id="avgPerDayText">เฉลี่ย 0 ต่อวัน</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #7c3aed, #c026d3);">
                <div class="kpi-title">สะสมตั้งแต่เปิดระบบ</div>
                <div class="kpi-value" id="visitsAllTime">0</div>
                <div class="kpi-sub">นับทุก visit ที่มาทาง Telemed</div>
                <i class="bi bi-bar-chart-steps icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="metric-strip">
        <div class="row g-3 text-center">
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">มีการลงสถานะเคส</div>
                <div class="metric-value" id="withStatus">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">จัดส่งยาถึงบ้าน</div>
                <div class="metric-value text-primary" id="homeDelivery">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">มารับยาเอง</div>
                <div class="metric-value text-warning" id="selfPickup">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">มีที่อยู่จัดส่ง</div>
                <div class="metric-value text-success" id="deliveryAddress">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">รอจัดส่ง/ติดตาม</div>
                <div class="metric-value text-danger" id="trackingWait">0</div>
            </div>
            <div class="col-lg col-6 metric-item">
                <div class="metric-label">ได้รับยาแล้ว</div>
                <div class="metric-value text-info" id="trackingReceived">0</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="panel-card">
                <h6 class="section-title"><i class="bi bi-graph-up-arrow text-primary"></i> แนวโน้ม Telemed รายเดือน</h6>
                <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="panel-card">
                <h6 class="section-title"><i class="bi bi-diagram-3-fill text-success"></i> สถานะการดูแลผู้ป่วย</h6>
                <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="section-title"><i class="bi bi-hospital-fill text-danger"></i> 10 คลินิกที่มี Telemed สูงสุด</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="clinicTable">
                        <thead class="table-light">
                            <tr>
                                <th>คลินิก</th>
                                <th class="text-end">Visits</th>
                                <th class="text-end">Patients</th>
                                <th style="width: 30%">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="section-title"><i class="bi bi-truck-front-fill text-warning"></i> สถานะติดตามการจัดส่งยา</h6>
                <div class="chart-wrap"><canvas id="trackingChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h6 class="section-title mb-0"><i class="bi bi-clock-history text-dark"></i> รายการ Telemed ล่าสุดในปีงบประมาณ</h6>
            <div class="d-flex flex-wrap gap-2">
                <span class="source-badge" id="sourceHis">HIS: -</span>
                <span class="source-badge" id="sourceApp">APP: -</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="latestTable">
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>HN</th>
                        <th>ชื่อผู้ป่วย</th>
                        <th>คลินิก</th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="5" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let monthlyChart = null;
let statusChart = null;
let trackingChart = null;

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

$(document).ready(function() {
    loadTelemedDashboard();
    setInterval(loadTelemedDashboard, 300000);
});

async function loadTelemedDashboard() {
    try {
        const response = await fetch('../../api/get_telemed_dashboard.php', { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูล Telemed ได้');
        }

        renderKpi(data);
        renderMonthlyChart(data.charts?.monthly || {});
        renderStatusChart(data.charts?.status || {});
        renderTrackingChart(data.charts?.tracking || {});
        renderClinicTable(data.clinic_ranking || []);
        renderLatestTable(data.latest_visits || []);
        renderSources(data.data_sources || {});
    } catch (error) {
        Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
    }
}

function renderKpi(data) {
    const kpi = data.kpi || {};
    const ops = data.operations || {};
    const range = data.fiscal_range || {};

    $('#visitsToday').text(numberText(kpi.visits_today));
    $('#patientsTodaySub').text(`ผู้ป่วย ${numberText(kpi.patients_today)} คน`);
    $('#visitsFy').text(numberText(kpi.visits_fy));
    $('#fiscalRangeText').text(`${thaiDate(range.start)} - ${thaiDate(range.end)} | ปีงบ ${data.fiscal_year || '-'}`);
    $('#patientsFy').text(numberText(kpi.patients_fy));
    $('#avgPerDayText').text(`เฉลี่ย ${numberText(kpi.avg_per_day, 1)} ต่อวัน`);
    $('#visitsAllTime').text(numberText(kpi.visits_all_time));

    $('#withStatus').text(numberText(ops.with_status));
    $('#homeDelivery').text(numberText(ops.home_delivery));
    $('#selfPickup').text(numberText(ops.self_pickup));
    $('#deliveryAddress').text(numberText(ops.delivery_address));
    $('#trackingWait').text(numberText(ops.tracking_wait));
    $('#trackingReceived').text(numberText(ops.tracking_received));
}

function renderMonthlyChart(data) {
    const ctx = document.getElementById('monthlyChart');
    if (monthlyChart) monthlyChart.destroy();

    monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'Visits',
                    data: data.visits || [],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.10)',
                    fill: true,
                    borderWidth: 3,
                    tension: .35
                },
                {
                    label: 'Patients',
                    data: data.patients || [],
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15,118,110,.10)',
                    fill: true,
                    borderWidth: 3,
                    tension: .35
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderStatusChart(data) {
    const ctx = document.getElementById('statusChart');
    if (statusChart) statusChart.destroy();

    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['ยังไม่มีข้อมูล'],
            datasets: [{
                data: data.data && data.data.length ? data.data : [1],
                backgroundColor: ['#2563eb', '#10b981', '#f97316', '#8b5cf6', '#ef4444', '#14b8a6', '#eab308', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderTrackingChart(data) {
    const ctx = document.getElementById('trackingChart');
    if (trackingChart) trackingChart.destroy();

    trackingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['ยังไม่มีข้อมูล'],
            datasets: [{
                label: 'จำนวนรายการ',
                data: data.data && data.data.length ? data.data : [0],
                backgroundColor: ['#ef4444', '#f97316', '#0ea5e9', '#10b981', '#8b5cf6', '#14b8a6', '#64748b', '#eab308'],
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

function renderClinicTable(rows) {
    if (!rows.length) {
        $('#clinicTable tbody').html('<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูลคลินิก Telemed</td></tr>');
        return;
    }

    const maxVisits = Math.max(...rows.map(row => Number(row.total_visits || 0)), 1);
    let html = '';
    rows.forEach(row => {
        const visits = Number(row.total_visits || 0);
        const percent = Math.round((visits / maxVisits) * 100);
        html += `<tr>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(row.clinic_name || '-')}</div>
                <div class="small text-muted">Clinic ${escapeHtml(row.clinic_code || '-')}</div>
            </td>
            <td class="text-end fw-bold text-primary">${numberText(visits)}</td>
            <td class="text-end fw-bold">${numberText(row.total_patients)}</td>
            <td>
                <div class="progress mini-progress">
                    <div class="progress-bar bg-primary" style="width: ${percent}%"></div>
                </div>
            </td>
        </tr>`;
    });
    $('#clinicTable tbody').html(html);
}

function renderLatestTable(rows) {
    if (!rows.length) {
        $('#latestTable tbody').html('<tr><td colspan="5" class="text-center text-muted">ยังไม่พบรายการ Telemed ล่าสุด</td></tr>');
        return;
    }

    let html = '';
    rows.forEach(row => {
        html += `<tr>
            <td>${escapeHtml(thaiDate(row.regdate))}</td>
            <td>${escapeHtml(row.timereg || '-')}</td>
            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">${escapeHtml(row.hn)}</span></td>
            <td>${escapeHtml(row.fullname || '-')}</td>
            <td>
                <div class="fw-bold">${escapeHtml(row.clinic_name || '-')}</div>
                <div class="small text-muted">Clinic ${escapeHtml(row.clinic_code || '-')}</div>
            </td>
        </tr>`;
    });
    $('#latestTable tbody').html(html);
}

function renderSources(source) {
    $('#sourceHis').text(`HIS: ${source.his || '-'}`);
    $('#sourceApp').text(`APP: ${source.telemed_app || '-'}`);
}
</script>

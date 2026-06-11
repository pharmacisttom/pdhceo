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
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #2563eb; padding-left: 12px; }
    .metric-item { border-left: 1px solid #e5e7eb; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 24px; font-weight: 800; }
    .domain-card { height: 100%; background: #fff; border: 1px solid #e2e8f0; border-top: 5px solid #10b981; border-radius: 15px; padding: 20px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); }
    .domain-card.blue { border-top-color: #2563eb; }
    .domain-card.danger { border-top-color: #dc2626; }
    .domain-title { color: #111827; font-weight: 800; margin-bottom: 10px; }
    .mini-row { display: flex; justify-content: space-between; gap: 14px; padding: 9px 0; border-bottom: 1px solid #eef2f7; }
    .mini-row:last-child { border-bottom: 0; }
    .mini-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .mini-value { color: #111827; font-size: 14px; font-weight: 800; text-align: right; }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    .status-overcrowd { animation: blinker 1.5s linear infinite; color: #fee2e2; font-weight: 800; }
    @keyframes blinker { 50% { opacity: 0.55; } }
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
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-building text-primary"></i> งานผู้ป่วยใน (IPD Bed Management & Analytics)</h4>
                <div class="text-secondary small">ติดตามเตียง ผู้ป่วย Admit/D/C วันนอน AdjRW CMI และประสิทธิภาพรายเดือน/ไตรมาส</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-primary p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>HIS Live</span>
            </div>
        </div>
    </div>

    <div class="section-title">สถานะเตียง Real-time แยกฐานทั่วไป 93 + ICU 9 เตียง</div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                <div class="kpi-title">เตียงใช้งาน</div>
                <div class="kpi-value" id="kpi_active">0</div>
                <div class="kpi-sub" id="kpi_overcrowd">จากทั้งหมด 102 เตียง</div>
                <i class="bi bi-person-bed icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #10b981, #047857);" id="card_available">
                <div class="kpi-title">เตียงว่างพร้อมรับ</div>
                <div class="kpi-value" id="kpi_available">0</div>
                <div class="kpi-sub">Available Bed</div>
                <i class="bi bi-check2-circle icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);" id="card_occ">
                <div class="kpi-title">อัตราครองเตียงปัจจุบัน</div>
                <div class="kpi-value" id="kpi_occ_rate">0.00%</div>
                <div class="kpi-sub">Real-time Occupancy</div>
                <i class="bi bi-pie-chart-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <div class="kpi-title">CMI ผู้ป่วยบนตึก</div>
                <div class="kpi-value" id="kpi_cmi">0.0000</div>
                <div class="kpi-sub">Current Case Mix Index</div>
                <i class="bi bi-activity icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-4">
            <div class="domain-card blue">
                <div class="domain-title"><i class="bi bi-building text-primary"></i> เตียงทั่วไป 93 เตียง</div>
                <div class="mini-row"><span class="mini-label">กำลัง Admit / เตียงว่าง</span><span class="mini-value" id="generalCurrentBeds">0 / 93</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปัจจุบัน</span><span class="mini-value" id="generalCurrentOcc">0.00%</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปีงบ</span><span class="mini-value" id="generalFyOcc">0.00%</span></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="domain-card danger">
                <div class="domain-title"><i class="bi bi-heart-pulse-fill text-danger"></i> ICU / กึ่งวิกฤต 9 เตียง</div>
                <div class="mini-row"><span class="mini-label">กำลัง Admit / เตียงว่าง</span><span class="mini-value" id="icuCurrentBeds">0 / 9</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปัจจุบัน</span><span class="mini-value" id="icuCurrentOcc">0.00%</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปีงบ</span><span class="mini-value" id="icuFyOcc">0.00%</span></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="domain-card">
                <div class="domain-title"><i class="bi bi-hospital-fill text-success"></i> รวมทั้งระบบ 102 เตียง</div>
                <div class="mini-row"><span class="mini-label">กำลัง Admit / เตียงว่าง</span><span class="mini-value" id="totalCurrentBeds">0 / 102</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปัจจุบัน</span><span class="mini-value" id="totalCurrentOcc">0.00%</span></div>
                <div class="mini-row"><span class="mini-label">อัตราครองเตียงปีงบ</span><span class="mini-value" id="totalFyOcc">0.00%</span></div>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวมปีงบประมาณ <span id="fiscalLabel">-</span></div>
    <div class="metric-strip">
        <div class="row g-3 text-center">
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">Admit</div>
                <div class="metric-value" id="fyAdmits">0</div>
            </div>
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">D/C</div>
                <div class="metric-value" id="fyDischarges">0</div>
            </div>
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">วันนอน</div>
                <div class="metric-value" id="fyPatientDays">0</div>
            </div>
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">Sum AdjRW</div>
                <div class="metric-value" id="fyAdjrw">0.0000</div>
            </div>
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">CMI ปีงบ</div>
                <div class="metric-value" id="fyCmi">0.0000</div>
            </div>
            <div class="col-lg-2 col-6 metric-item">
                <div class="metric-label">Occ. ปีงบ</div>
                <div class="metric-value" id="fyOcc">0.00%</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> แนวโน้ม Admit / D/C / วันนอน รายเดือนในปีงบ</h6>
                <div class="chart-canvas"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-building-fill text-success"></i> ผู้ป่วยกำลัง Admit แยกตาม Ward</h6>
                <div class="chart-canvas"><canvas id="wardChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-brain text-danger"></i> Stroke Unit เฉพาะผู้ป่วยรหัส I60-I69</h6>
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="metric-label">กำลัง Admit</div>
                        <div class="metric-value text-danger" id="strokeActive">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">Admit ปีงบ</div>
                        <div class="metric-value" id="strokeAdmits">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">D/C ปีงบ</div>
                        <div class="metric-value" id="strokeDischarges">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">วันนอน</div>
                        <div class="metric-value" id="strokePatientDays">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">Sum AdjRW</div>
                        <div class="metric-value" id="strokeAdjrw">0.0000</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">CMI</div>
                        <div class="metric-value" id="strokeCmi">0.0000</div>
                    </div>
                </div>
                <div class="small text-muted mt-3">นิยาม: วินิจฉัยหลักจาก `opd.odiag.dxtype='1'` และ ICD-10 `I60-I69`</div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-virus2 text-danger"></i> โรค Stroke Unit สูงสุดในปีงบนี้</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="strokeDiagTable">
                        <thead class="table-light">
                            <tr>
                                <th>ICD-10</th>
                                <th>ชื่อโรค</th>
                                <th class="text-end">จำนวน</th>
                                <th style="width: 30%">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="4" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-capsule-pill text-primary"></i> การใช้ยา RTPA ในผู้ป่วย Stroke</h6>
                <div class="small text-muted mb-3" id="rtpaDrugName">RTPA</div>
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="metric-label">OPD Visit</div>
                        <div class="metric-value text-primary" id="rtpaOpdVisits">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">IPD AN</div>
                        <div class="metric-value text-success" id="rtpaIpdVisits">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">OPD จำนวนยา</div>
                        <div class="metric-value" id="rtpaOpdAmount">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">IPD จำนวนยา</div>
                        <div class="metric-value" id="rtpaIpdAmount">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">OPD มูลค่า</div>
                        <div class="metric-value" id="rtpaOpdPrice">0</div>
                    </div>
                    <div class="col-6">
                        <div class="metric-label">IPD มูลค่า</div>
                        <div class="metric-value" id="rtpaIpdPrice">0</div>
                    </div>
                </div>
                <div class="small text-muted mt-3">นิยาม: `codedrug='RTPA'` จาก `opd.drug_order_opd` และ `ipd.drug_order_ipd` เฉพาะผู้ป่วย Stroke `I60-I69`</div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-primary"></i> แนวโน้มการใช้ RTPA รายเดือน</h6>
                <div class="chart-canvas"><canvas id="rtpaChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-danger"></i> เปรียบเทียบผลงานรายไตรมาส</h6>
                <div class="chart-canvas"><canvas id="quarterChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-grid-3x3-gap-fill text-success"></i> สรุปไตรมาส</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="quarterTable">
                        <thead class="table-light">
                            <tr>
                                <th>ไตรมาส</th>
                                <th class="text-end">Admit</th>
                                <th class="text-end">D/C</th>
                                <th class="text-end">Occ.</th>
                                <th class="text-end">CMI</th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="5" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-warning"></i> แนวโน้มอัตราครองเตียงย้อนหลัง 5 ปี</h6>
                <div class="chart-canvas"><canvas id="fyOccChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-success"></i> สถิติ IPD ย้อนหลังตามปีงบประมาณ</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle border mb-0" id="fyTable">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>ปีงบ</th>
                                <th>Admit</th>
                                <th>วันนอน</th>
                                <th>AdjRW</th>
                                <th>Occ.</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-card-list text-primary"></i> รายชื่อผู้ป่วยในที่จำหน่ายแล้วในปีงบประมาณปัจจุบัน <span id="dischargedFyLabel"></span></h6>
        <div class="table-responsive">
            <table id="ipdTable" class="table table-hover table-striped align-middle border mb-0">
                <thead class="table-light">
                    <tr>
                        <th>AN</th>
                        <th>HN</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>Ward</th>
                        <th>วันที่ D/C</th>
                        <th class="text-center">DRG</th>
                        <th class="text-end">AdjRW</th>
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
let monthlyChartInst = null;
let wardChartInst = null;
let quarterChartInst = null;
let occChartInst = null;
let rtpaChartInst = null;

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

                if (chart.options.indexAxis === 'y') {
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
    loadIpdDashboard();
    setInterval(loadIpdDashboard, 300000);
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

function loadIpdDashboard() {
    Swal.fire({ title: 'กำลังโหลดข้อมูล IPD...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

    $.ajax({
        url: '../../api/get_ipd_dashboard.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            Swal.close();
            if (res.status !== 'success') {
                Swal.fire('ข้อผิดพลาด', res.message || 'ไม่สามารถโหลดข้อมูลได้', 'error');
                return;
            }

            renderRealTimeKPIs(res.current_stats || {});
            renderBedSplit(res.bed_split || {});
            renderFiscalSummary(res);
            renderStrokeUnit(res.stroke_unit || {});
            renderRtpaUsage(res.stroke_unit && res.stroke_unit.rtpa ? res.stroke_unit.rtpa : {});
            renderMonthlyChart(res.charts ? res.charts.monthly : {});
            renderWardChart(res.charts ? res.charts.ward_active : {});
            renderQuarterChart(res.charts ? res.charts.quarters : {});
            renderQuarterTable(res.charts && res.charts.quarters ? res.charts.quarters.rows : []);
            renderFiscalYearStats(res.fy_stats || []);
            renderDischargedPatients(res.discharged_list || res.active_list || [], res.current_fiscal_year);
        },
        error: function(xhr) {
            Swal.close();
            console.error(xhr.responseText);
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบฐานข้อมูลได้', 'error');
        }
    });
}

function renderRealTimeKPIs(stats) {
    const activeCases = Number(stats.active_cases || 0);
    const availableBeds = Number(stats.available_beds || 0);
    const occRate = Number(stats.occ_rate || 0);
    const cmi = Number(stats.cmi || 0);
    const overcrowd = Number(stats.overcrowd || 0);

    $('#kpi_active').text(numberText(activeCases));
    $('#kpi_available').text(numberText(availableBeds));
    $('#kpi_occ_rate').text(numberText(occRate, 2) + '%');
    $('#kpi_cmi').text(numberText(cmi, 4));

    if (overcrowd > 0) {
        $('#kpi_overcrowd').html(`<span class="status-overcrowd"><i class="bi bi-exclamation-triangle-fill"></i> เตียงเสริมล้น ${numberText(overcrowd)} เตียง</span>`);
        $('#card_available').css('background', 'linear-gradient(135deg, #ef4444, #b91c1c)');
        $('#kpi_available').text('เต็ม');
        $('#card_occ').css('background', 'linear-gradient(135deg, #ef4444, #b91c1c)');
    } else {
        $('#kpi_overcrowd').text(`จากทั้งหมด ${numberText(stats.total_beds || 102)} เตียง`);
        $('#card_available').css('background', 'linear-gradient(135deg, #10b981, #047857)');
        $('#card_occ').css('background', occRate >= 85 ? 'linear-gradient(135deg, #ea580c, #c2410c)' : 'linear-gradient(135deg, #f97316, #c2410c)');
    }
}

function renderBedSplit(data) {
    const current = data.current || {};
    const fiscal = data.fiscal_year || {};
    setSplitBed('general', current.general || {}, fiscal.general || {});
    setSplitBed('icu', current.icu || {}, fiscal.icu || {});
    setSplitBed('total', current.total || {}, fiscal.total || {});
}

function setSplitBed(key, current, fiscal) {
    const active = Number(current.active || 0);
    const beds = Number(current.beds || 0);
    const available = Number(current.available || 0);
    $(`#${key}CurrentBeds`).text(`${numberText(active)} / ${numberText(beds)} (ว่าง ${numberText(available)})`);
    $(`#${key}CurrentOcc`).text(`${numberText(current.occ_rate, 2)}%`);
    $(`#${key}FyOcc`).text(`${numberText(fiscal.occ_rate, 2)}%`);
}

function renderFiscalSummary(res) {
    const summary = res.fy_summary || {};
    const range = res.fiscal_range || {};
    $('#fiscalLabel').text(`${res.current_fiscal_year || '-'} (${thaiDate(range.start)} - ${thaiDate(range.end)})`);
    $('#fyAdmits').text(numberText(summary.admits));
    $('#fyDischarges').text(numberText(summary.discharges));
    $('#fyPatientDays').text(numberText(summary.patient_days));
    $('#fyAdjrw').text(numberText(summary.sum_adjrw, 4));
    $('#fyCmi').text(numberText(summary.cmi, 4));
    $('#fyOcc').text(numberText(summary.occ_rate, 2) + '%');
}

function renderStrokeUnit(data) {
    $('#strokeActive').text(numberText(data.active));
    $('#strokeAdmits').text(numberText(data.admits));
    $('#strokeDischarges').text(numberText(data.discharges));
    $('#strokePatientDays').text(numberText(data.patient_days));
    $('#strokeAdjrw').text(numberText(data.sum_adjrw, 4));
    $('#strokeCmi').text(numberText(data.cmi, 4));
    renderStrokeDiagTable(data.diagnosis || []);
}

function renderRtpaUsage(data) {
    const drug = data.drug || {};
    const opd = data.opd || {};
    const ipd = data.ipd || {};
    const monthly = data.monthly || {};

    $('#rtpaDrugName').text(`${drug.code || 'RTPA'}: ${drug.name || 'ALTEPLASE (rt-PA)'}`);
    $('#rtpaOpdVisits').text(numberText(opd.visits));
    $('#rtpaIpdVisits').text(numberText(ipd.visits));
    $('#rtpaOpdAmount').text(numberText(opd.amount, 2));
    $('#rtpaIpdAmount').text(numberText(ipd.amount, 2));
    $('#rtpaOpdPrice').text(numberText(opd.price, 2));
    $('#rtpaIpdPrice').text(numberText(ipd.price, 2));

    const ctx = document.getElementById('rtpaChart');
    if (rtpaChartInst) rtpaChartInst.destroy();

    rtpaChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthly.labels || [],
            datasets: [
                { label: 'OPD', data: monthly.opd || [], backgroundColor: '#2563eb', borderRadius: 6 },
                { label: 'IPD', data: monthly.ipd || [], backgroundColor: '#10b981', borderRadius: 6 }
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

function renderStrokeDiagTable(rows) {
    if (!rows || rows.length === 0) {
        $('#strokeDiagTable tbody').html('<tr><td colspan="4" class="text-center text-muted">ยังไม่พบข้อมูล Stroke Unit ในปีงบนี้</td></tr>');
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
    $('#strokeDiagTable tbody').html(html);
}

function renderMonthlyChart(data) {
    const ctx = document.getElementById('monthlyChart');
    if (monthlyChartInst) monthlyChartInst.destroy();

    monthlyChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Admit', data: data.admits || [], backgroundColor: '#3b82f6', borderRadius: 6, yAxisID: 'y' },
                { label: 'D/C', data: data.discharges || [], backgroundColor: '#10b981', borderRadius: 6, yAxisID: 'y' },
                { type: 'line', label: 'วันนอน', data: data.patient_days || [], borderColor: '#f97316', backgroundColor: '#f97316', borderWidth: 3, pointRadius: 5, tension: 0.3, yAxisID: 'yDays' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, visibleValueLabels: { color: '#0f172a' } },
            scales: {
                y: { beginAtZero: true, position: 'left' },
                yDays: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
}

function renderWardChart(data) {
    const ctx = document.getElementById('wardChart');
    if (wardChartInst) wardChartInst.destroy();

    wardChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{ label: 'กำลัง Admit', data: data.data || [], backgroundColor: '#2563eb', borderRadius: 6 }]
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

function renderQuarterChart(data) {
    const ctx = document.getElementById('quarterChart');
    if (quarterChartInst) quarterChartInst.destroy();

    quarterChartInst = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                { label: 'Admit', data: data.admits || [], backgroundColor: '#3b82f6', borderRadius: 6, yAxisID: 'y' },
                { label: 'D/C', data: data.discharges || [], backgroundColor: '#10b981', borderRadius: 6, yAxisID: 'y' },
                { type: 'line', label: 'Occ. (%)', data: data.occ_rate || [], borderColor: '#ef4444', backgroundColor: '#ef4444', borderWidth: 3, pointRadius: 5, tension: 0.3, yAxisID: 'yRate' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                visibleValueLabels: {
                    formatter: (value, dataset) => dataset.yAxisID === 'yRate' ? numberText(value, 2) + '%' : numberText(value),
                    color: '#0f172a'
                }
            },
            scales: {
                y: { beginAtZero: true, position: 'left' },
                yRate: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: value => value + '%' } }
            }
        }
    });
}

function renderQuarterTable(rows) {
    if (!rows || rows.length === 0) {
        $('#quarterTable tbody').html('<tr><td colspan="5" class="text-center text-muted">ไม่พบข้อมูลไตรมาส</td></tr>');
        return;
    }

    let html = '';
    rows.forEach(row => {
        html += `<tr>
            <td>
                <div class="fw-bold">${escapeHtml(row.label)}</div>
                <div class="small text-muted">${thaiDate(row.start)} - ${thaiDate(row.end)}</div>
            </td>
            <td class="text-end fw-bold">${numberText(row.admits)}</td>
            <td class="text-end fw-bold">${numberText(row.discharges)}</td>
            <td class="text-end"><span class="badge ${Number(row.occ_rate || 0) >= 85 ? 'bg-danger' : 'bg-success'}">${numberText(row.occ_rate, 2)}%</span></td>
            <td class="text-end fw-bold text-primary">${numberText(row.cmi, 4)}</td>
        </tr>`;
    });
    $('#quarterTable tbody').html(html);
}

function renderFiscalYearStats(fyData) {
    let html = '';
    const chartLabels = [];
    const chartData = [];

    if (fyData.length > 0) {
        [...fyData].reverse().forEach(row => {
            const occRate = Number(row.occ_rate || 0);
            html += `<tr class="text-center">
                <td class="fw-bold text-dark">ปี ${escapeHtml(row.fiscal_year)}</td>
                <td>${numberText(row.total_admits)}</td>
                <td>${numberText(row.total_patient_days)}</td>
                <td class="text-primary fw-bold">${numberText(row.sum_adjrw, 4)}</td>
                <td><span class="badge ${occRate >= 85 ? 'bg-danger' : 'bg-success'}">${numberText(occRate, 2)}%</span></td>
            </tr>`;
        });

        fyData.forEach(row => {
            chartLabels.push('ปี ' + row.fiscal_year);
            chartData.push(Number(row.occ_rate || 0));
        });
    } else {
        html = '<tr><td colspan="5" class="text-center text-muted">ไม่พบข้อมูลรายปี</td></tr>';
    }

    $('#fyTable tbody').html(html);

    if (occChartInst) occChartInst.destroy();

    occChartInst = new Chart(document.getElementById('fyOccChart'), {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'อัตราครองเตียง (%)',
                data: chartData,
                backgroundColor: chartData.map(val => val >= 85 ? '#ef4444' : '#3b82f6'),
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                visibleValueLabels: { formatter: value => numberText(value, 2) + '%', color: '#0f172a' }
            },
            scales: { y: { beginAtZero: true, max: 120, ticks: { callback: value => value + '%' } } }
        }
    });
}

function renderDischargedPatients(data, fiscalYear) {
    let html = '';
    $('#dischargedFyLabel').text(fiscalYear ? `(ปีงบ ${fiscalYear})` : '');

    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#ipdTable')) {
        $('#ipdTable').DataTable().destroy();
    }

    if (data.length > 0) {
        data.forEach(row => {
            const adjrwText = row.adjrw !== null && row.adjrw !== '' ? numberText(row.adjrw, 4) : '0.0000';
            html += `<tr>
                <td>${escapeHtml(row.an)}</td>
                <td>${escapeHtml(row.hn)}</td>
                <td>${escapeHtml(row.fullname)}</td>
                <td>${escapeHtml(row.ward_name)}</td>
                <td>${escapeHtml(row.datedsc)}</td>
                <td class="text-center">${escapeHtml(row.drg || '-')}</td>
                <td class="text-end">${adjrwText}</td>
            </tr>`;
        });
    } else {
        html = '<tr><td colspan="7" class="text-center text-muted">ไม่พบข้อมูลผู้ป่วย D/C ในปีงบประมาณนี้</td></tr>';
    }

    $('#ipdTable tbody').html(html);

    if ($.fn.DataTable && data.length > 0) {
        $('#ipdTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
            pageLength: 10,
            order: [[4, 'desc']]
        });
    }
}
</script>

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
    .topbar { border-radius: 16px; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; transition: transform 0.2s, box-shadow 0.2s; min-height: 145px; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -8px; bottom: -22px; font-size: 82px; opacity: 0.16; }
    .kpi-title { font-size: 14px; opacity: 0.92; font-weight: 600; }
    .kpi-value { font-size: 31px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #2563eb; padding-left: 12px; }
    .chart-block { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; min-height: 350px; }
    .chart-canvas { position: relative; height: 285px; }
    .metric-strip { background: #fff; border-radius: 15px; padding: 18px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); }
    .metric-item { border-left: 1px solid #e5e7eb; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 24px; font-weight: 800; }
    .table-card { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    .bed-domain { height: 100%; background: #fff; border: 1px solid #e2e8f0; border-top: 5px solid #2563eb; border-radius: 15px; padding: 18px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); }
    .bed-domain.icu { border-top-color: #dc2626; }
    .bed-domain.total { border-top-color: #0f766e; }
    .bed-domain-value { color: #0f172a; font-size: 26px; font-weight: 800; }
    .bed-progress { height: 8px; overflow: hidden; border-radius: 99px; background: #e2e8f0; }
    .bed-progress > div { height: 100%; border-radius: inherit; }
    .signal-card { height: 100%; padding: 15px; border: 1px solid #e2e8f0; border-left: 5px solid #22c55e; border-radius: 13px; background: #fff; }
    .signal-card.warning { border-left-color: #f59e0b; }
    .signal-card.danger { border-left-color: #ef4444; }
    .freshness-strip { padding: 12px 16px; border: 1px solid #bfdbfe; border-radius: 13px; background: #eff6ff; color: #1e3a8a; }
    @media (max-width: 991px) {
        .content { margin-left: 0; padding: 18px; }
        .metric-item { border-left: 0; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px; }
        .metric-item:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
    }
</style>

<div class="content">
    <div class="topbar bg-white p-3 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-speedometer2 text-primary"></i> แผงควบคุมผู้บริหาร (Executive Realtime Dashboard)</h4>
                <div class="text-secondary small">ติดตามบริการผู้ป่วย เตียง ผลผลิต และสัญญาณสำคัญสำหรับการตัดสินใจแบบ Real-time</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-primary p-2 rounded-3 me-2"><i class="bi bi-shield-lock"></i> สิทธิ์: <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2 rounded-3"><span class="status-dot"></span>HIS Live</span>
            </div>
        </div>
    </div>

    <div class="section-title">ภาพรวมปีงบประมาณ <span id="fiscalYear">-</span></div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0ea5e9, #0369a1);">
                <div class="kpi-title">ผู้ป่วยนอกสะสม</div>
                <div class="kpi-value" id="opdFy">0</div>
                <div class="kpi-sub" id="opdSub">วันนี้ 0 ครั้ง</div>
                <i class="bi bi-people-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #6366f1, #4338ca);">
                <div class="kpi-title">ผู้ป่วยในสะสม</div>
                <div class="kpi-value" id="ipdFy">0</div>
                <div class="kpi-sub">ยอด Admit ในปีงบนี้</div>
                <i class="bi bi-person-vcard-fill icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #10b981, #047857);">
                <div class="kpi-title">เตียงว่าง ณ ปัจจุบัน</div>
                <div class="kpi-value" id="availableBeds">0</div>
                <div class="kpi-sub" id="bedSub">กำลัง Admit 0 / 102 เตียง</div>
                <i class="bi bi-check2-circle icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #f97316, #c2410c);">
                <div class="kpi-title">อัตราครองเตียงปีงบนี้</div>
                <div class="kpi-value" id="fyOccRate">0.00%</div>
                <div class="kpi-sub" id="occSub">Real-time 0.00%</div>
                <i class="bi bi-pie-chart-fill icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="metric-strip mb-4">
        <div class="row g-3 text-center">
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">D/C ปีงบนี้</div>
                <div class="metric-value" id="dischargeFy">0</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">วันนอนรวม</div>
                <div class="metric-value" id="patientDays">0</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">Sum AdjRW</div>
                <div class="metric-value" id="sumAdjrw">0.0000</div>
            </div>
            <div class="col-lg-3 col-6 metric-item">
                <div class="metric-label">CMI</div>
                <div class="metric-value" id="cmi">0.0000</div>
            </div>
        </div>
    </div>

    <div class="freshness-strip mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <span><i class="bi bi-database-check"></i> <strong>สถานะข้อมูล:</strong> <span id="dataFreshness">กำลังตรวจสอบ...</span></span>
            <span><i class="bi bi-clock-history"></i> อัปเดตหน้า Dashboard: <strong id="generatedAt">-</strong></span>
        </div>
    </div>

    <div class="metric-strip mb-4">
        <div class="row g-3 text-center">
            <div class="col-lg-4 metric-item">
                <div class="metric-label">วันนอนเฉลี่ยต่อผู้จำหน่าย (ALOS)</div>
                <div class="metric-value"><span id="alos">0.00</span> <small class="fs-6">วัน</small></div>
            </div>
            <div class="col-lg-4 metric-item">
                <div class="metric-label">ผู้ป่วยนอกเฉลี่ยต่อวัน</div>
                <div class="metric-value"><span id="averageDailyOpd">0.0</span> <small class="fs-6">ครั้ง</small></div>
            </div>
            <div class="col-lg-4 metric-item">
                <div class="metric-label">อัตราจำหน่ายเทียบ Admit</div>
                <div class="metric-value"><span id="dischargeToAdmitRate">0.00</span><small class="fs-6">%</small></div>
            </div>
        </div>
    </div>

    <div class="section-title">สถานะเตียงแยกฐานทั่วไป 93 + ICU 9 เตียง</div>
    <div class="row g-3 mb-4" id="bedSplitGrid"></div>

    <div class="section-title">สัญญาณสำคัญสำหรับผู้บริหาร</div>
    <div class="row g-3 mb-4" id="managementSignals"></div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-primary"></i> เปรียบเทียบผลงานรายไตรมาสในปีงบประมาณ</h6>
                <div class="chart-canvas"><canvas id="quarterCompareChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="table-card mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-grid-3x3-gap-fill text-success"></i> สรุปไตรมาส</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="quarterTable">
                        <thead class="table-light">
                            <tr>
                                <th>ไตรมาส</th>
                                <th class="text-end">OPD</th>
                                <th class="text-end">IPD</th>
                                <th class="text-end">D/C</th>
                                <th class="text-end">Occ.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> แนวโน้มบริการรายเดือนในปีงบประมาณ</h6>
                <div class="chart-canvas"><canvas id="serviceTrendChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hospital text-success"></i> สถานะเตียงปัจจุบัน</h6>
                <div class="chart-canvas"><canvas id="bedChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-info"></i> สัดส่วนสิทธิการรักษาปีงบนี้</h6>
                <div class="chart-canvas"><canvas id="payerChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clipboard2-pulse-fill text-danger"></i> คลินิก/หน่วยบริการที่มีปริมาณสูงสุด</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="clinicTable">
                        <thead class="table-light">
                            <tr>
                                <th>หน่วยบริการ</th>
                                <th class="text-end">จำนวนครั้ง</th>
                                <th style="width: 35%">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="3" class="text-center text-muted">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let serviceTrendChart = null;
let quarterCompareChart = null;
let bedChart = null;
let payerChart = null;

const visibleValueLabels = {
    id: 'visibleValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const { ctx, chartArea } = chart;
        const options = pluginOptions || {};
        const formatter = options.formatter || ((value) => numberText(value));

        ctx.save();
        ctx.font = options.font || '700 11px sans-serif';
        ctx.fillStyle = options.color || '#111827';
        ctx.strokeStyle = options.strokeColor || '#ffffff';
        ctx.lineWidth = 4;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const rawValue = dataset.data[index];
                const numericValue = Number(rawValue || 0);
                if (!Number.isFinite(numericValue) || (numericValue === 0 && options.hideZero !== false)) return;

                const label = formatter(numericValue, dataset, index);
                let x = element.x;
                let y = element.y;
                let align = 'center';
                let baseline = 'middle';

                if (chart.config.type === 'line') {
                    y -= 12 + (datasetIndex * 13);
                    baseline = 'bottom';
                } else if (chart.config.type === 'doughnut') {
                    const props = element.getProps(['x', 'y', 'startAngle', 'endAngle', 'innerRadius', 'outerRadius'], true);
                    const angle = (props.startAngle + props.endAngle) / 2;
                    const radius = (props.innerRadius + props.outerRadius) / 2;
                    x = props.x + Math.cos(angle) * radius;
                    y = props.y + Math.sin(angle) * radius;
                } else if (chart.options.indexAxis === 'y') {
                    x = Math.min(element.x + 8, chartArea.right - 8);
                    align = 'left';
                }

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
    loadExecutiveRealtime();
    setInterval(loadExecutiveRealtime, 300000);
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

function loadExecutiveRealtime() {
    $.ajax({
        url: 'api/get_executive_realtime.php',
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.status !== 'success') {
                Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถโหลดข้อมูลผู้บริหารได้', 'error');
                return;
            }

            renderKpis(res);
            renderBedSplit(res.bed_split || {});
            renderManagementSignals(res.management_signals || []);
            renderFreshness(res);
            renderQuarterCompare(res.charts.quarters || {});
            renderQuarterTable(res.charts.quarters ? res.charts.quarters.rows : []);
            renderServiceTrend(res.charts.service_trend);
            renderBedChart(res.charts.bed);
            renderPayerChart(res.charts.payer);
            renderClinicTable(res.charts.clinics);
        },
        error: function() {
            Swal.fire('เชื่อมต่อล้มเหลว', 'ไม่สามารถดึงข้อมูล Real-time จาก HIS ได้', 'error');
        }
    });
}

function renderKpis(res) {
    const kpi = res.kpi || {};
    const range = res.fiscal_range || {};

    $('#fiscalYear').text(`${res.fiscal_year || '-'} (${thaiDate(range.start)} - ${thaiDate(range.end)})`);
    $('#opdFy').text(numberText(kpi.opd_fy));
    $('#opdSub').text(`วันนี้ ${numberText(kpi.opd_today)} ครั้ง`);
    $('#ipdFy').text(numberText(kpi.ipd_fy));
    $('#availableBeds').text(numberText(kpi.available_beds));
    $('#bedSub').text(`กำลัง Admit ${numberText(kpi.active_ipd)} / ${numberText(kpi.total_beds)} เตียง`);
    $('#fyOccRate').text(numberText(kpi.fy_occ_rate, 2) + '%');
    $('#occSub').text(`Real-time ${numberText(kpi.current_occ_rate, 2)}%`);
    $('#dischargeFy').text(numberText(kpi.discharge_fy));
    $('#patientDays').text(numberText(kpi.patient_days));
    $('#sumAdjrw').text(numberText(kpi.sum_adjrw, 4));
    $('#cmi').text(numberText(kpi.cmi, 4));
    $('#alos').text(numberText(kpi.alos, 2));
    $('#averageDailyOpd').text(numberText(kpi.average_daily_opd, 1));
    $('#dischargeToAdmitRate').text(numberText(kpi.discharge_to_admit_rate, 2));
}

function renderFreshness(res) {
    const freshness = res.data_freshness || {};
    const trial = freshness.trial_balance_month
        ? `งบทดลองล่าสุด ${freshness.trial_balance_month} (${numberText(freshness.trial_balance_rows)} บัญชี)`
        : 'ยังไม่พบงบทดลอง';
    $('#dataFreshness').text(`HIS ถึง ${thaiDate(freshness.his_as_of)} · ${trial}`);
    $('#generatedAt').text(res.generated_at || '-');
}

function renderBedSplit(data) {
    const current = data.current || {};
    const fiscal = data.fiscal_year || {};
    const rows = [
        ['เตียงทั่วไป', current.general || {}, fiscal.general || {}, '93 เตียง ไม่รวม ICU', '#2563eb', ''],
        ['ICU / กึ่งวิกฤต', current.icu || {}, fiscal.icu || {}, '9 เตียง', '#dc2626', 'icu'],
        ['รวมทั้งระบบ', current.total || {}, fiscal.total || {}, '102 เตียง', '#0f766e', 'total']
    ];
    $('#bedSplitGrid').html(rows.map(([name, now, fy, note, color, css]) => {
        const rate = Number(now.occ_rate || 0);
        const crowd = Number(now.overcrowd || 0);
        return `<div class="col-xl-4">
            <div class="bed-domain ${css}">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="fw-bold" style="color:${color}">${name}</div><div class="small text-secondary">${note}</div></div>
                    ${crowd > 0 ? `<span class="badge bg-danger">ล้น ${numberText(crowd)}</span>` : ''}
                </div>
                <div class="bed-domain-value mt-3">${numberText(now.active)} / ${numberText(now.beds)}</div>
                <div class="small text-secondary mb-2">กำลัง Admit / เตียงฐาน · ว่าง ${numberText(now.available)}</div>
                <div class="bed-progress"><div style="width:${Math.min(rate,100)}%;background:${color}"></div></div>
                <div class="d-flex justify-content-between small mt-2"><span>ปัจจุบัน <b>${numberText(rate,2)}%</b></span><span>ปีงบ <b>${numberText(fy.occ_rate,2)}%</b></span></div>
            </div>
        </div>`;
    }).join(''));
}

function renderManagementSignals(signals) {
    $('#managementSignals').html((signals || []).map(item => `<div class="col-xl-4 col-md-6">
        <div class="signal-card ${escapeHtml(item.level)}">
            <div class="fw-bold text-dark mb-1">${escapeHtml(item.title)}</div>
            <div class="small text-secondary">${escapeHtml(item.detail)}</div>
        </div>
    </div>`).join(''));
}

function renderQuarterCompare(data) {
    const ctx = document.getElementById('quarterCompareChart');
    if (quarterCompareChart) quarterCompareChart.destroy();

    quarterCompareChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'OPD',
                    data: data.opd || [],
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'IPD Admit',
                    data: data.ipd || [],
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'D/C',
                    data: data.discharge || [],
                    backgroundColor: '#f97316',
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'อัตราครองเตียง (%)',
                    data: data.occ_rate || [],
                    borderColor: '#ef4444',
                    backgroundColor: '#ef4444',
                    borderWidth: 3,
                    pointRadius: 5,
                    tension: 0.3,
                    yAxisID: 'yRate'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                visibleValueLabels: {
                    formatter: (value, dataset) => dataset.yAxisID === 'yRate' ? numberText(value, 2) + '%' : numberText(value),
                    color: '#0f172a',
                    font: '700 10px sans-serif'
                }
            },
            scales: {
                y: { beginAtZero: true, position: 'left' },
                yRate: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { callback: value => value + '%' }
                }
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
        const dateRange = `${thaiDate(row.start)} - ${thaiDate(row.end)}`;
        html += `<tr>
            <td>
                <div class="fw-bold text-dark">${escapeHtml(row.label)}</div>
                <div class="small text-muted">${dateRange}</div>
            </td>
            <td class="text-end fw-bold">${numberText(row.opd)}</td>
            <td class="text-end fw-bold">${numberText(row.ipd)}</td>
            <td class="text-end fw-bold">${numberText(row.discharge)}</td>
            <td class="text-end"><span class="badge ${Number(row.occ_rate || 0) >= 85 ? 'bg-danger' : 'bg-success'}">${numberText(row.occ_rate, 2)}%</span></td>
        </tr>`;
    });

    $('#quarterTable tbody').html(html);
}

function renderServiceTrend(data) {
    const ctx = document.getElementById('serviceTrendChart');
    if (serviceTrendChart) serviceTrendChart.destroy();

    serviceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'OPD',
                    data: data.opd || [],
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.10)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3
                },
                {
                    label: 'IPD Admit',
                    data: data.ipd || [],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.10)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3
                },
                {
                    label: 'D/C',
                    data: data.discharge || [],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.08)',
                    fill: false,
                    tension: 0.35,
                    borderWidth: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                visibleValueLabels: {
                    formatter: (value) => numberText(value),
                    color: '#0f172a'
                }
            },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderBedChart(data) {
    const ctx = document.getElementById('bedChart');
    if (bedChart) bedChart.destroy();

    bedChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || [],
            datasets: [{
                data: data.data || [],
                backgroundColor: ['#f97316', '#10b981'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom' },
                visibleValueLabels: {
                    formatter: (value) => numberText(value),
                    color: '#0f172a'
                }
            }
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
            datasets: [{
                label: 'จำนวนครั้ง',
                data: data.data || [],
                backgroundColor: ['#2563eb', '#10b981', '#f97316', '#ef4444', '#8b5cf6', '#14b8a6'],
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                visibleValueLabels: {
                    formatter: (value) => numberText(value),
                    color: '#0f172a'
                }
            },
            scales: { x: { beginAtZero: true } }
        }
    });
}

function renderClinicTable(rows) {
    if (!rows || rows.length === 0) {
        $('#clinicTable tbody').html('<tr><td colspan="3" class="text-center text-muted">ไม่พบข้อมูลหน่วยบริการ</td></tr>');
        return;
    }

    const max = Math.max(...rows.map(row => Number(row.total || 0)), 1);
    let html = '';

    rows.forEach(row => {
        const total = Number(row.total || 0);
        const percent = Math.round((total / max) * 100);
        html += `<tr>
            <td class="fw-bold text-dark">${escapeHtml(row.label)}</td>
            <td class="text-end fw-bold">${numberText(total)}</td>
            <td>
                <div class="progress" style="height: 9px;">
                    <div class="progress-bar bg-primary" style="width: ${percent}%"></div>
                </div>
            </td>
        </tr>`;
    });

    $('#clinicTable tbody').html(html);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}
</script>

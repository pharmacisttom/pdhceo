<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../includes/functions.php';

$currentMonth = date('Y-m');
$user_role = $_SESSION['role'] ?? 'Executive';
?>

<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
    .content { margin-left: 260px; padding: 30px; background: #f2f5fa; min-height: 100vh; }
    .kpi-card { border: none; border-radius: 15px; color: #fff; overflow: hidden; position: relative; min-height: 138px; transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
    .kpi-card .icon-bg { position: absolute; right: -10px; bottom: -22px; font-size: 78px; opacity: 0.16; }
    .kpi-title { font-size: 13px; opacity: 0.94; font-weight: 700; }
    .kpi-value { font-size: 28px; line-height: 1.15; font-weight: 800; margin: 8px 0 4px; letter-spacing: 0; }
    .kpi-sub { font-size: 12px; opacity: 0.86; font-weight: 500; }
    .chart-block, .table-card, .filter-card, .metric-strip, .domain-card { background: #fff; border-radius: 15px; padding: 22px; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04); margin-bottom: 24px; }
    .chart-canvas { position: relative; height: 310px; }
    .chart-canvas.tall { height: 380px; }
    .section-title { font-size: 18px; font-weight: 800; color: #111827; margin: 22px 0 14px; border-left: 5px solid #0f766e; padding-left: 12px; }
    .metric-item { border-left: 1px solid #e5e7eb; }
    .metric-item:first-child { border-left: 0; }
    .metric-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .metric-value { color: #0f172a; font-size: 23px; font-weight: 800; }
    .status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #22c55e; margin-right: 6px; }
    .domain-card { height: 100%; border-top: 4px solid #0f766e; }
    .domain-card.warn { border-top-color: #f97316; }
    .domain-card.danger { border-top-color: #dc2626; }
    .domain-card.blue { border-top-color: #2563eb; }
    .domain-card.gray { border-top-color: #64748b; }
    .domain-title { font-weight: 800; color: #111827; margin-bottom: 12px; }
    .mini-row { display: flex; justify-content: space-between; gap: 14px; padding: 9px 0; border-bottom: 1px solid #eef2f7; }
    .mini-row:last-child { border-bottom: 0; }
    .mini-label { color: #64748b; font-size: 13px; font-weight: 700; }
    .mini-value { color: #111827; font-size: 15px; font-weight: 800; text-align: right; }
    .target-badge { border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 800; }
    .good { background: #dcfce7; color: #166534; }
    .watch { background: #ffedd5; color: #9a3412; }
    .bad { background: #fee2e2; color: #991b1b; }
    .info-note { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; color: #475569; font-size: 13px; }
    @media (max-width: 991px) {
        .content { margin-left: 0; padding: 18px; }
        .metric-item { border-left: 0; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 12px; }
        .metric-item:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
    }
</style>

<div class="content">
    <div class="topbar bg-white p-3 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-6">
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-wallet2 text-success"></i> Dashboard การเงิน พัสดุ และ TPS</h4>
                <div class="text-secondary small">ติดตาม PlanFin, ลูกหนี้/เรียกเก็บ, เจ้าหนี้, ต้นทุน/ผลิตภาพ และสภาพคล่องสำหรับผู้บริหาร</div>
            </div>
            <div class="col-lg-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar3"></i></span>
                    <input type="month" id="filterMonth" class="form-control bg-light border-start-0 fw-bold" value="<?= htmlspecialchars($currentMonth, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="col-lg-3 text-lg-end">
                <a href="data_entry.php" class="btn btn-success rounded-3 w-100 py-2 fw-bold shadow-sm">
                    <i class="bi bi-pencil-square"></i> บันทึกข้อมูลรายเดือน
                </a>
            </div>
        </div>
    </div>

    <div class="filter-card py-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="fw-bold text-dark"><i class="bi bi-clipboard2-data text-success"></i> ภาพรวมเดือน <span id="monthLabel">-</span> ปีงบประมาณ <span id="fiscalYear">-</span></div>
                <div class="small text-muted" id="dataStatus">กำลังโหลดข้อมูลจากระบบ...</div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge bg-light text-dark border p-2 me-2"><i class="bi bi-person-badge text-primary"></i> <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark p-2"><span class="status-dot"></span>Finance + HIS Live</span>
                <button type="button" class="btn btn-outline-warning btn-sm ms-2 mt-2 mt-lg-0 fw-bold" id="btnDataReadiness">
                    <i class="bi bi-exclamation-triangle"></i> ตรวจข้อมูลที่ขาด
                </button>
            </div>
        </div>
    </div>

    <div class="section-title">ตัวชี้วัดเร่งด่วนสำหรับผู้บริหาร</div>
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #0f766e, #0d9488);">
                <div class="kpi-title">รายได้จริงรวม</div>
                <div class="kpi-value" id="kpiIncome">0</div>
                <div class="kpi-sub">ค่ารักษา + ยา + Lab/X-Ray</div>
                <i class="bi bi-cash-coin icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <div class="kpi-title">ค่าใช้จ่ายดำเนินงาน</div>
                <div class="kpi-value" id="kpiExpense">0</div>
                <div class="kpi-sub">ค่าสาธารณูปโภค + ค่าตอบแทน + วัสดุ</div>
                <i class="bi bi-receipt-cutoff icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <div class="kpi-title">กำไร/ขาดทุนดำเนินงาน</div>
                <div class="kpi-value" id="kpiProfit">0</div>
                <div class="kpi-sub">รายได้ดำเนินงานหักค่าใช้จ่าย</div>
                <i class="bi bi-graph-up-arrow icon-bg"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-3" style="background: linear-gradient(135deg, #475569, #334155);">
                <div class="kpi-title">Cash Ratio / NWC</div>
                <div class="kpi-value" id="kpiCashRatio">0.00</div>
                <div class="kpi-sub" id="kpiNwc">NWC 0 บาท</div>
                <i class="bi bi-bank icon-bg"></i>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="domain-card">
                <div class="domain-title"><i class="bi bi-bar-chart-line-fill text-success"></i> 1. รายได้และค่าใช้จ่าย (PlanFin)</div>
                <div class="mini-row"><span class="mini-label">รายได้รวมที่รับจริง</span><span class="mini-value" id="pfIncome">0</span></div>
                <div class="mini-row"><span class="mini-label">ค่าใช้จ่ายดำเนินงานจริง</span><span class="mini-value" id="pfExpense">0</span></div>
                <div class="mini-row"><span class="mini-label">Expense / Income</span><span class="mini-value" id="pfExpenseRate">0%</span></div>
                <div class="mt-3"><span class="target-badge watch" id="pfStatus">รอข้อมูลแผน PlanFin</span></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="domain-card blue">
                <div class="domain-title"><i class="bi bi-arrow-repeat text-primary"></i> 2. ลูกหนี้และการเรียกเก็บ (RCM)</div>
                <div class="mini-row"><span class="mini-label">ยอด Billing เดือนนี้</span><span class="mini-value" id="rcmBilling">0</span></div>
                <div class="mini-row"><span class="mini-label">Collection Period</span><span class="mini-value" id="rcmCollection">0 วัน</span></div>
                <div class="mini-row"><span class="mini-label">D/C เดือนนี้</span><span class="mini-value" id="rcmDischarge">0 ราย</span></div>
                <div class="mt-3"><span class="target-badge" id="rcmStatus">เป้าหมาย <= 60 วัน</span></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="domain-card warn">
                <div class="domain-title"><i class="bi bi-journal-check text-warning"></i> 3. เจ้าหนี้การค้า (AP)</div>
                <div class="mini-row"><span class="mini-label">เจ้าหนี้ค้างจ่ายโดยประมาณ</span><span class="mini-value" id="apBalance">0</span></div>
                <div class="mini-row"><span class="mini-label">Payable Days</span><span class="mini-value" id="apDays">0 วัน</span></div>
                <div class="mini-row"><span class="mini-label">รายการจัดซื้อ</span><span class="mini-value" id="apProcure">0 รายการ</span></div>
                <div class="mt-3"><span class="target-badge" id="apStatus">เป้าหมาย <= 90/180 วัน</span></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="domain-card danger">
                <div class="domain-title"><i class="bi bi-box-seam text-danger"></i> 4. ต้นทุนและผลิตภาพ (Cost & Productivity)</div>
                <div class="mini-row"><span class="mini-label">Material Cost</span><span class="mini-value" id="costMaterial">0</span></div>
                <div class="mini-row"><span class="mini-label">วันนอนรวม / เตียงเปิดจริง</span><span class="mini-value" id="costPatientDays">0 / 0</span></div>
                <div class="mini-row"><span class="mini-label">Bed Occupancy</span><span class="mini-value" id="costOccRate">0%</span></div>
                <div class="mini-row"><span class="mini-label">Sum AdjRW / MC per AdjRW</span><span class="mini-value" id="costAdjrw">0 / 0</span></div>
                <div class="mt-3"><span class="target-badge" id="costStatus">เป้าหมายครองเตียง >= 80%</span></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="domain-card gray">
                <div class="domain-title"><i class="bi bi-droplet-half text-secondary"></i> 5. สภาพคล่องและผลลัพธ์ (Liquidity & Outcome)</div>
                <div class="mini-row"><span class="mini-label">Current Assets</span><span class="mini-value" id="liqAssets">0</span></div>
                <div class="mini-row"><span class="mini-label">Current Liabilities</span><span class="mini-value" id="liqLiabilities">0</span></div>
                <div class="mini-row"><span class="mini-label">Cash & Equivalents</span><span class="mini-value" id="liqCash">0</span></div>
                <div class="mini-row"><span class="mini-label">Operating Profit</span><span class="mini-value" id="liqProfit">0</span></div>
                <div class="mt-3"><span class="target-badge" id="liqStatus">ติดตามรายเดือน</span></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow text-success"></i> แนวโน้มรายได้ ค่าใช้จ่าย และกำไร/ขาดทุน ปีงบประมาณนี้</h6>
                <div class="chart-canvas"><canvas id="profitTrendChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill text-primary"></i> โครงสร้างรายได้เดือนนี้</h6>
                <div class="chart-canvas"><canvas id="incomeStructureChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-fill text-danger"></i> ต้นทุนวัสดุและสภาพคล่อง</h6>
                <div class="chart-canvas"><canvas id="costLiquidityChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hospital-fill text-warning"></i> ผลิตภาพ IPD: วันนอน, AdjRW, Occupancy</h6>
                <div class="chart-canvas"><canvas id="productivityChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="chart-block">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hourglass-split text-warning"></i> AP Aging โดยประมาณ</h6>
                <div class="chart-canvas"><canvas id="apAgingChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="table-card">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary"></i> Action Plan สำหรับผู้บริหาร</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="actionTable">
                        <thead class="table-light">
                            <tr>
                                <th>หมวด</th>
                                <th>สิ่งที่ต้องติดตาม</th>
                                <th>ผู้รับผิดชอบ</th>
                                <th>ความถี่</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="info-note mt-3">
                    หมายเหตุ: ตัวชี้วัดบางรายการใช้ค่าประมาณจากข้อมูลที่มีอยู่ในระบบรายเดือน เพราะยังไม่มีช่องบันทึก AR Aging, Claim Lag, Denial Rate และ PlanFin รายไตรมาสโดยตรง
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>

<script>
let profitTrendChart = null;
let incomeStructureChart = null;
let costLiquidityChart = null;
let productivityChart = null;
let apAgingChart = null;
let latestFinanceDiagnostics = null;
let lastDiagnosticMonth = null;

const visibleValueLabels = {
    id: 'visibleValueLabels',
    afterDatasetsDraw(chart, args, pluginOptions) {
        const { ctx, chartArea } = chart;
        const options = pluginOptions || {};
        const formatter = options.formatter || ((value) => shortMoney(value));

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
    loadFinanceData();
    document.getElementById('filterMonth').addEventListener('change', loadFinanceData);
    document.getElementById('btnDataReadiness').addEventListener('click', () => showDataReadiness(latestFinanceDiagnostics, true));
});

async function loadFinanceData() {
    const month = document.getElementById('filterMonth').value;
    Swal.fire({
        title: 'กำลังดึงข้อมูลการเงินและ HIS...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch(`../../api/get_finance_dashboard.php?month=${encodeURIComponent(month)}`, { credentials: 'same-origin' });
        const data = await response.json();
        Swal.close();
        if (data.status !== 'success') {
            Swal.fire('แจ้งเตือน', data.message || 'ไม่สามารถโหลดข้อมูลได้', 'warning');
            return;
        }
        renderFinance(data);
        latestFinanceDiagnostics = data.data_diagnostics || null;
        showDataReadiness(latestFinanceDiagnostics, false);
    } catch (error) {
        Swal.close();
        Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
    }
}

function renderFinance(data) {
    const sections = data.sections || {};
    const planfin = sections.planfin || {};
    const rcm = sections.rcm || {};
    const ap = sections.ap || {};
    const cost = sections.cost || {};
    const liquidity = sections.liquidity || {};

    setText('monthLabel', data.month?.label || '-');
    setText('fiscalYear', data.fiscal?.year || '-');
    setText('dataStatus', data.data_found ? 'มีข้อมูลบันทึกรายเดือนแล้ว ใช้ร่วมกับข้อมูล HIS แบบเรียลไทม์' : 'ยังไม่พบข้อมูลบันทึกรายเดือน ระบบจะแสดง 0 และคำนวณเฉพาะข้อมูล HIS ที่มี');

    setText('kpiIncome', moneyText(planfin.total_income));
    setText('kpiExpense', moneyText(planfin.total_expense));
    setText('kpiProfit', moneyText(planfin.operating_profit));
    setText('kpiCashRatio', numberText(liquidity.cash_ratio, 2));
    setText('kpiNwc', `NWC ${moneyText(liquidity.nwc)}`);

    setText('pfIncome', moneyText(planfin.total_income));
    setText('pfExpense', moneyText(planfin.total_expense));
    setText('pfExpenseRate', `${numberText(planfin.expense_to_income, 2)}%`);
    setBadge('pfStatus', 'รอข้อมูลแผน PlanFin รายไตรมาส', 'watch');

    setText('rcmBilling', moneyText(rcm.billing_total));
    setText('rcmCollection', `${numberText(rcm.collection_days_proxy, 1)} วัน`);
    setText('rcmDischarge', `${numberText(rcm.discharge_count)} ราย`);
    setBadge('rcmStatus', `เป้าหมาย <= ${rcm.collection_target_days || 60} วัน`, Number(rcm.collection_days_proxy || 0) <= 60 ? 'good' : 'bad');

    setText('apBalance', moneyText(ap.payable_balance_proxy));
    setText('apDays', `${numberText(ap.payable_days_proxy, 1)} วัน`);
    setText('apProcure', `${numberText(ap.procurement_count)} รายการ`);
    setBadge('apStatus', `เป้าหมาย <= ${ap.payable_target_days || 90} วัน`, Number(ap.payable_days_proxy || 0) <= Number(ap.payable_target_days || 90) ? 'good' : 'bad');

    setText('costMaterial', moneyText(cost.material_cost));
    setText('costPatientDays', `${numberText(cost.patient_days)} / ${numberText(cost.bed_count)}`);
    setText('costOccRate', `${numberText(cost.bed_occ_rate, 2)}%`);
    setText('costAdjrw', `${numberText(cost.sum_adjrw, 4)} / ${moneyText(cost.material_per_adjrw)}`);
    setBadge('costStatus', 'เป้าหมายครองเตียง >= 80%', Number(cost.bed_occ_rate || 0) >= 80 ? 'good' : 'watch');

    setText('liqAssets', moneyText(liquidity.current_assets));
    setText('liqLiabilities', moneyText(liquidity.current_liabilities));
    setText('liqCash', moneyText(liquidity.cash_equivalents));
    setText('liqProfit', moneyText(liquidity.operating_profit));
    setBadge('liqStatus', Number(liquidity.operating_profit || 0) >= 0 ? 'กำไรดำเนินงานเป็นบวก' : 'ต้องติดตามผลขาดทุน', Number(liquidity.operating_profit || 0) >= 0 ? 'good' : 'bad');

    renderCharts(data);
    renderActionTable();
}

function showDataReadiness(diagnostics, force = false) {
    if (!diagnostics) return;
    const month = document.getElementById('filterMonth').value;
    const issues = diagnostics.issues || [];
    const charts = diagnostics.charts || [];
    const missingCharts = charts.filter(item => !item.ready);

    if (!force && ((issues.length === 0 && missingCharts.length === 0) || lastDiagnosticMonth === month)) return;
    lastDiagnosticMonth = month;

    if (issues.length === 0 && missingCharts.length === 0) {
        Swal.fire({
            icon: 'success',
            title: 'ข้อมูลพร้อมแสดงผล',
            text: 'แหล่งข้อมูลสำคัญสำหรับกราฟและ KPI ของเดือนนี้พร้อมใช้งาน',
            confirmButtonText: 'ตกลง'
        });
        return;
    }

    const levelIcon = { danger: 'bi-x-circle-fill text-danger', warning: 'bi-exclamation-triangle-fill text-warning', info: 'bi-info-circle-fill text-primary' };
    const issueHtml = issues.map(item => `
        <div class="text-start border rounded-3 p-3 mb-2">
            <div class="fw-bold"><i class="bi ${levelIcon[item.level] || levelIcon.info}"></i> ${escapeHtml(item.area)}</div>
            <div class="small text-secondary mt-1">${escapeHtml(item.message)}</div>
            <div class="small mt-2"><strong>ต้องเพิ่ม:</strong> ${escapeHtml(item.action)}</div>
            <div class="small text-muted"><strong>แหล่งข้อมูล:</strong> ${escapeHtml(item.source)}</div>
        </div>
    `).join('');
    const chartHtml = missingCharts.length ? `
        <div class="text-start fw-bold mt-3 mb-2">กราฟที่ยังแสดงไม่ครบ</div>
        <ul class="text-start small mb-0">
            ${missingCharts.map(item => `<li>${escapeHtml(item.chart)}: ขาด ${escapeHtml(item.missing)}</li>`).join('')}
        </ul>
    ` : '';
    const trial = diagnostics.trial_balance || {};
    const trialHtml = `<div class="alert alert-info text-start small mt-3 mb-0">
        <strong>งบทดลอง:</strong> ${trial.ready ? `ล่าสุด ${escapeHtml(trial.month)} (${numberText(trial.row_count)} บัญชี)` : 'ยังไม่พบข้อมูล'}
    </div>`;

    Swal.fire({
        icon: 'warning',
        title: `ตรวจพบข้อมูลที่ต้องเพิ่มเติม (${issues.length} รายการ)`,
        html: `<div style="max-height:55vh;overflow:auto;padding-right:4px">${issueHtml}${chartHtml}${trialHtml}</div>`,
        width: 760,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-pencil-square"></i> ไปหน้าบันทึกข้อมูล',
        cancelButtonText: 'ปิด',
        confirmButtonColor: '#0f766e'
    }).then(result => {
        if (result.isConfirmed) window.location.href = 'data_entry.php';
    });
}

function renderCharts(data) {
    const charts = data.charts || {};
    const income = data.income_chart || {};

    profitTrendChart = replaceChart(profitTrendChart, 'profitTrendChart', {
        type: 'bar',
        data: {
            labels: charts.labels || [],
            datasets: [
                { type: 'bar', label: 'รายได้', data: charts.income || [], backgroundColor: '#0f766e', borderRadius: 8 },
                { type: 'bar', label: 'ค่าใช้จ่าย', data: charts.expense || [], backgroundColor: '#dc2626', borderRadius: 8 },
                { type: 'line', label: 'กำไร/ขาดทุน', data: charts.profit || [], borderColor: '#2563eb', backgroundColor: '#2563eb', borderWidth: 3, pointRadius: 5, tension: 0.3 }
            ]
        },
        options: moneyChartOptions({ maxTicksLimit: 8 })
    });

    incomeStructureChart = replaceChart(incomeStructureChart, 'incomeStructureChart', {
        type: 'doughnut',
        data: {
            labels: ['ค่ารักษาพยาบาล', 'ค่ายา/เวชภัณฑ์', 'Lab/X-Ray'],
            datasets: [{
                data: [income.treatment || 0, income.drug || 0, income.lab || 0],
                backgroundColor: ['#2563eb', '#0f766e', '#f97316'],
                borderWidth: 0
            }]
        },
        options: doughnutOptions()
    });

    costLiquidityChart = replaceChart(costLiquidityChart, 'costLiquidityChart', {
        type: 'bar',
        data: {
            labels: charts.labels || [],
            datasets: [
                { label: 'Material Cost', data: charts.material || [], backgroundColor: '#dc2626', borderRadius: 8 },
                { label: 'Cash', data: charts.cash || [], backgroundColor: '#64748b', borderRadius: 8 }
            ]
        },
        options: moneyChartOptions({ maxTicksLimit: 8 })
    });

    productivityChart = replaceChart(productivityChart, 'productivityChart', {
        type: 'bar',
        data: {
            labels: charts.labels || [],
            datasets: [
                { label: 'วันนอน', data: charts.patient_days || [], backgroundColor: '#f97316', borderRadius: 8, yAxisID: 'y' },
                { type: 'line', label: 'AdjRW', data: charts.sum_adjrw || [], borderColor: '#2563eb', backgroundColor: '#2563eb', borderWidth: 3, pointRadius: 5, tension: 0.3, yAxisID: 'y1' },
                { type: 'line', label: 'Occupancy %', data: charts.occ_rate || [], borderColor: '#0f766e', backgroundColor: '#0f766e', borderWidth: 3, pointRadius: 5, tension: 0.3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: chartPlugins(value => numberText(value)),
            scales: {
                x: { ticks: { font: { family: 'Sarabun' }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: { beginAtZero: true, position: 'left', ticks: { callback: value => numberText(value) } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: value => numberText(value) } }
            }
        }
    });

    apAgingChart = replaceChart(apAgingChart, 'apAgingChart', {
        type: 'bar',
        data: {
            labels: charts.ap_aging?.labels || [],
            datasets: [{
                label: 'ยอดเจ้าหนี้โดยประมาณ',
                data: charts.ap_aging?.values || [],
                backgroundColor: ['#22c55e', '#f59e0b', '#f97316', '#dc2626'],
                borderRadius: 8
            }]
        },
        options: moneyChartOptions()
    });
}

function renderActionTable() {
    const rows = [
        ['PlanFin', 'เตรียมรายได้/ค่าใช้จ่ายจริงเทียบแผนรายไตรมาส และคุม variance ±5%', 'บัญชี/การเงิน', 'รายเดือน + ไตรมาส'],
        ['RCM', 'สรุป AR แยกสิทธิ, Claim Lag Time, Denial Rate และเคส D/C ที่ยังไม่ส่ง claim', 'ศูนย์จัดเก็บรายได้/ประกันสุขภาพ', 'รายสัปดาห์'],
        ['AP', 'สรุปเจ้าหนี้ค้างจ่ายและ Aging Report แยกประเภทยา วัสดุ และทั่วไป', 'พัสดุ/คลังยา/บัญชีเจ้าหนี้', 'รายเดือน'],
        ['Cost', 'ติดตาม Material Cost, Patient Days, Bed Count, Sum AdjRW และ Occupancy', 'IT/เวชระเบียน/พยาบาล', 'รายวัน + รายเดือน'],
        ['Liquidity', 'ติดตาม Current Assets, Current Liabilities, Cash Ratio, NWC และ Operating Profit', 'บริหารทั่วไป/บัญชี', 'รายเดือน']
    ];

    document.querySelector('#actionTable tbody').innerHTML = rows.map(row => `
        <tr>
            <td class="fw-bold text-primary">${escapeHtml(row[0])}</td>
            <td>${escapeHtml(row[1])}</td>
            <td>${escapeHtml(row[2])}</td>
            <td>${escapeHtml(row[3])}</td>
        </tr>
    `).join('');
}

function replaceChart(currentChart, canvasId, config) {
    if (currentChart) currentChart.destroy();
    return new Chart(document.getElementById(canvasId), config);
}

function moneyChartOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: chartPlugins(value => shortMoney(value)),
        scales: {
            x: { ticks: { font: { family: 'Sarabun' }, maxRotation: 0, autoSkip: true, maxTicksLimit: extra.maxTicksLimit || 10 } },
            y: { beginAtZero: true, ticks: { callback: value => shortMoney(value) } }
        }
    };
}

function doughnutOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '56%',
        plugins: chartPlugins(value => shortMoney(value))
    };
}

function chartPlugins(formatter) {
    return {
        legend: { position: 'bottom', labels: { font: { family: 'Sarabun' } } },
        tooltip: { callbacks: { label: context => `${context.dataset.label || context.label}: ${moneyText(context.raw)}` } },
        visibleValueLabels: { formatter, color: '#111827', hideZero: true }
    };
}

function setText(id, text) {
    document.getElementById(id).textContent = text ?? '-';
}

function setBadge(id, text, status) {
    const element = document.getElementById(id);
    element.textContent = text;
    element.className = `target-badge ${status}`;
}

function moneyText(value) {
    return Number(value || 0).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function shortMoney(value) {
    const number = Number(value || 0);
    if (Math.abs(number) >= 1000000) return `${(number / 1000000).toLocaleString('th-TH', { maximumFractionDigits: 1 })}ล.`;
    if (Math.abs(number) >= 1000) return `${(number / 1000).toLocaleString('th-TH', { maximumFractionDigits: 0 })}พ.`;
    return number.toLocaleString('th-TH', { maximumFractionDigits: 0 });
}

function numberText(value, digits = 0) {
    return Number(value || 0).toLocaleString('th-TH', { minimumFractionDigits: digits, maximumFractionDigits: digits });
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

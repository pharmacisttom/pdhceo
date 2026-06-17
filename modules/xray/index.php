<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$defaultFiscalYear = (((int)$now->format('n')) >= 10 ? (int)$now->format('Y') + 1 : (int)$now->format('Y')) + 543;
?>
<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>
<style>
.content{margin-left:260px;padding:30px;min-height:100vh;background:#f3f6fb}.hero,.panel,.metric-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 5px 16px rgba(15,23,42,.05)}.hero{padding:24px;background:linear-gradient(125deg,#0f172a,#1d4ed8);color:#fff}.metric-card{height:100%;padding:18px;border-left:5px solid #2563eb}.metric-card.green{border-left-color:#0f766e}.metric-card.red{border-left-color:#dc2626}.metric-card.amber{border-left-color:#f59e0b}.metric-label{font-size:13px;font-weight:800;color:#64748b}.metric-value{font-size:28px;font-weight:900;color:#0f172a;line-height:1.15}.metric-sub{font-size:12px;color:#64748b}.panel{padding:22px}.chart-canvas{height:320px;position:relative}.small-note{font-size:12px;color:#64748b}.section-title{margin:24px 0 14px;padding-left:12px;border-left:5px solid #2563eb;font-size:19px;font-weight:900;color:#0f172a}.table-sm{font-size:13px}@media(max-width:991px){.content{margin-left:0;padding:18px}}
</style>
<main class="content">
    <section class="hero mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <div class="small fw-bold opacity-75">DIAGNOSTIC IMAGING COST & SERVICE DASHBOARD</div>
                <h3 class="fw-bold mb-1"><i class="bi bi-radioactive"></i> ค่าบริหาร ต้นทุน และข้อมูล X-ray / CT Scan</h3>
                <div class="opacity-75">สรุปข้อมูลบริการและมูลค่าตามปีงบประมาณ จาก HIS OPD/IPD X-ray orders</div>
            </div>
            <div class="col-lg-3">
                <label class="small fw-bold">ปีงบประมาณ</label>
                <input type="number" id="fiscalYear" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars((string)$defaultFiscalYear, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-lg-2 d-grid">
                <label class="small fw-bold">&nbsp;</label>
                <button class="btn btn-light btn-lg fw-bold" id="btnReload"><i class="bi bi-arrow-repeat"></i> โหลดข้อมูล</button>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="metric-label">จำนวน Order</div><div class="metric-value" id="orderCount">0</div><div class="metric-sub" id="periodText">-</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card green"><div class="metric-label">จำนวน Visit/Admission ที่ใช้บริการ</div><div class="metric-value" id="encounterCount">0</div><div class="metric-sub">นับ OPD visit และ IPD admission</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card amber"><div class="metric-label">จำนวนรายการตรวจ</div><div class="metric-value" id="examQty">0</div><div class="metric-sub">รวม amount หรือ 1 ต่อ order</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card red"><div class="metric-label">มูลค่ารวม</div><div class="metric-value" id="grossValue">0</div><div class="metric-sub">คำนวณจาก amount x price</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card green"><div class="metric-label">Support</div><div class="metric-value" id="supportValue">0</div><div class="metric-sub">ยอดสนับสนุน</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card red"><div class="metric-label">Non-support</div><div class="metric-value" id="nonsupportValue">0</div><div class="metric-sub">ยอดไม่สนับสนุน/เรียกเก็บ</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="metric-label">Film Good / Bad</div><div class="metric-value"><span id="filmGood">0</span> / <span id="filmBad">0</span></div><div class="metric-sub">คุณภาพฟิล์มจาก order</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card amber"><div class="metric-label">ผู้ป่วยไม่ซ้ำ</div><div class="metric-value" id="patientCount">0</div><div class="metric-sub">Distinct HN</div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <section class="panel h-100">
                <h5 class="fw-bold mb-1"><i class="bi bi-graph-up text-primary"></i> แนวโน้มรายเดือน</h5>
                <div class="small-note mb-3">เปรียบเทียบจำนวน X-ray, CT Scan และมูลค่ารวม</div>
                <div class="chart-canvas"><canvas id="monthlyChart"></canvas></div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="panel h-100">
                <h5 class="fw-bold mb-1"><i class="bi bi-pie-chart-fill text-success"></i> แยก OPD/IPD และ Modalities</h5>
                <div class="small-note mb-3">Order count ตาม X-ray / CT Scan</div>
                <div class="chart-canvas"><canvas id="serviceChart"></canvas></div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="panel h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-table text-primary"></i> รายการตรวจที่มีมูลค่าสูงสุด</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>รายการ</th><th>ประเภท</th><th class="text-end">จำนวน</th><th class="text-end">มูลค่า</th></tr></thead>
                        <tbody id="topExamRows"><tr><td colspan="4" class="text-center text-muted">กำลังโหลด...</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="panel h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-check2-square text-success"></i> สถานะ Order</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>Status</th><th class="text-end">จำนวน</th></tr></thead>
                        <tbody id="statusRows"><tr><td colspan="2" class="text-center text-muted">กำลังโหลด...</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="section-title">Diagnosis ที่เกี่ยวข้องกับผู้ใช้บริการ X-ray / CT Scan</div>
    <div class="row g-4">
        <div class="col-xl-6"><section class="panel"><h5 class="fw-bold mb-3">OPD Principal Diagnosis</h5><div class="table-responsive"><table class="table table-sm"><thead class="table-light"><tr><th>ICD-10</th><th>คำอธิบาย</th><th class="text-end">Visit</th></tr></thead><tbody id="opdDiagRows"></tbody></table></div></section></div>
        <div class="col-xl-6"><section class="panel"><h5 class="fw-bold mb-3">IPD Principal Diagnosis</h5><div class="table-responsive"><table class="table table-sm"><thead class="table-light"><tr><th>ICD-10</th><th>คำอธิบาย</th><th class="text-end">Case</th></tr></thead><tbody id="ipdDiagRows"></tbody></table></div></section></div>
    </div>
</main>
<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
let monthlyChart=null,serviceChart=null;
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('btnReload').addEventListener('click',loadXray);document.getElementById('fiscalYear').addEventListener('change',loadXray);loadXray()});
async function loadXray(){try{const fy=document.getElementById('fiscalYear').value;const r=await fetch(`../../api/get_xray_dashboard.php?fiscal_year=${encodeURIComponent(fy)}`,{credentials:'same-origin'});const d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'โหลดข้อมูลไม่สำเร็จ');renderXray(d)}catch(e){Swal.fire('โหลดข้อมูล X-ray / CT Scan ไม่สำเร็จ',e.message,'error')}}
function renderXray(d){const s=d.summary||{};set('orderCount',num(s.order_count));set('encounterCount',num(s.encounter_count));set('patientCount',num(s.patient_count));set('examQty',num(s.exam_qty));set('grossValue',money(s.gross_value));set('supportValue',money(s.support_value));set('nonsupportValue',money(s.nonsupport_value));set('filmGood',num(s.filmgood));set('filmBad',num(s.filmbad));set('periodText',`${d.period?.start||'-'} ถึง ${d.period?.end||'-'}`);renderMonthly(d.monthly||{});renderService(d.service_breakdown||[]);renderTopExams(d.top_exams||[]);renderStatuses(d.status_breakdown||[]);renderDiag('opdDiagRows',d.diagnoses?.opd||[]);renderDiag('ipdDiagRows',d.diagnoses?.ipd||[])}
function renderMonthly(m){if(monthlyChart)monthlyChart.destroy();monthlyChart=new Chart(document.getElementById('monthlyChart'),{type:'bar',data:{labels:m.labels||[],datasets:[{label:'X-ray Orders',data:m.xray_orders||[],backgroundColor:'#2563eb',borderRadius:6},{label:'CT Orders',data:m.ct_orders||[],backgroundColor:'#dc2626',borderRadius:6},{type:'line',label:'มูลค่า',data:m.gross_value||[],borderColor:'#0f766e',backgroundColor:'#0f766e',borderWidth:3,tension:.35,yAxisID:'y1'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{callback:v=>shortMoney(v)}}}}})}
function renderService(rows){if(serviceChart)serviceChart.destroy();serviceChart=new Chart(document.getElementById('serviceChart'),{type:'doughnut',data:{labels:rows.map(x=>`${x.service_type} ${x.modality}`),datasets:[{data:rows.map(x=>x.order_count),backgroundColor:['#2563eb','#dc2626','#0f766e','#f59e0b'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'58%',plugins:{legend:{position:'bottom'}}}})}
function renderTopExams(rows){document.getElementById('topExamRows').innerHTML=rows.length?rows.map(x=>`<tr><td><div class="fw-bold">${esc(x.namexray)}</div><div class="small-note">${esc(x.codexray)}</div></td><td>${esc(x.modality)}</td><td class="text-end">${num(x.exam_qty)}</td><td class="text-end fw-bold">${money(x.gross_value)}</td></tr>`).join(''):'<tr><td colspan="4" class="text-center text-muted">ยังไม่มีข้อมูล</td></tr>'}
function renderStatuses(rows){document.getElementById('statusRows').innerHTML=rows.length?rows.map(x=>`<tr><td>${esc(x.status_xray||'-')}</td><td class="text-end fw-bold">${num(x.total)}</td></tr>`).join(''):'<tr><td colspan="2" class="text-center text-muted">ยังไม่มีข้อมูล</td></tr>'}
function renderDiag(id,rows){document.getElementById(id).innerHTML=rows.length?rows.map(x=>`<tr><td class="fw-bold">${esc(x.diag)}</td><td>${esc(x.descrip)}</td><td class="text-end fw-bold">${num(x.total)}</td></tr>`).join(''):'<tr><td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูล diagnosis</td></tr>'}
function set(id,v){document.getElementById(id).textContent=v}
function num(v,d=0){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:d,maximumFractionDigits:d})}
function money(v){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2})}
function shortMoney(v){v=Number(v||0);return Math.abs(v)>=1e6?`${num(v/1e6,1)}ล.`:Math.abs(v)>=1e3?`${num(v/1e3,0)}พ.`:num(v)}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
</script>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_login();
$currentMonth = date('Y-m');
?>
<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>
<style>
.content{margin-left:260px;padding:30px;min-height:100vh;background:#f3f6fb}.hero{position:relative;overflow:hidden;padding:28px;border-radius:22px;color:#fff;background:linear-gradient(125deg,#0f172a,#0f766e 58%,#0284c7);box-shadow:0 18px 38px rgba(15,23,42,.18)}.hero:after{content:"";position:absolute;right:-80px;top:-130px;width:330px;height:330px;border:55px solid rgba(255,255,255,.08);border-radius:50%}.panel{height:100%;padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:17px;box-shadow:0 5px 17px rgba(15,23,42,.05)}.section-title{margin:28px 0 15px;padding-left:13px;border-left:6px solid #0891b2;color:#0f172a;font-size:20px;font-weight:800}.period-strip{margin-top:16px;padding:16px;background:#fff;border:1px solid #dbe4ee;border-radius:17px;box-shadow:0 5px 17px rgba(15,23,42,.05)}.period-item{height:100%;padding:13px;border:1px solid #e2e8f0;border-left:5px solid #94a3b8;border-radius:12px;background:#f8fafc}.period-item.current{border-left-color:#10b981}.period-item.watch{border-left-color:#f59e0b}.period-item.stale,.period-item.missing{border-left-color:#ef4444}.period-range{font-size:14px;font-weight:800;color:#0f172a}.period-status{font-size:11px;font-weight:800}.summary-card{position:relative;overflow:hidden;color:#fff;border:0}.summary-card i{position:absolute;right:15px;bottom:-14px;font-size:68px;opacity:.15}.summary-value{font-size:28px;font-weight:800}.summary-label{font-size:13px;font-weight:700;opacity:.9}.metric-card{border-top:5px solid #94a3b8}.metric-card.good{border-top-color:#10b981}.metric-card.bad{border-top-color:#ef4444}.metric-card.pending{border-top-color:#f59e0b}.metric-code{font-size:12px;font-weight:800;color:#0e7490}.metric-value{margin:7px 0;color:#0f172a;font-size:27px;font-weight:800}.metric-name{min-height:43px;font-weight:800;color:#334155}.pill{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}.pill.good{color:#166534;background:#dcfce7}.pill.bad{color:#991b1b;background:#fee2e2}.pill.pending{color:#92400e;background:#fef3c7}.formula{margin-top:12px;padding:10px;border-radius:10px;color:#475569;background:#f8fafc;border:1px solid #e2e8f0;font-size:12px;min-height:58px}.chart-box{height:330px}.bed-track{height:9px;overflow:hidden;border-radius:99px;background:#e2e8f0}.bed-fill{height:100%;width:0;background:#0f766e;border-radius:inherit}.readiness{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #eef2f7}.readiness:last-child{border-bottom:0}.readiness-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#dcfce7;color:#166534}.readiness-icon.off{background:#fef3c7;color:#92400e}.spec-table th{white-space:nowrap;color:#334155;background:#f1f5f9}.spec-table td,.spec-table th{padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:middle}.nav-tabs .nav-link{font-weight:800;color:#64748b}.nav-tabs .nav-link.active{color:#0f766e}.small-note{font-size:12px;color:#64748b}@media(max-width:991px){.content{margin-left:0;padding:18px}.chart-box{height:280px}}
</style>
<main class="content">
<section class="hero">
 <div class="row align-items-center g-3 position-relative" style="z-index:1">
  <div class="col-lg-6"><div class="small fw-bold opacity-75 mb-2">FINANCIAL COMMAND CENTER · TPS 1.2 / 1.3 / OUTCOME</div><h3 class="fw-bold mb-2"><i class="bi bi-briefcase-fill"></i> CFO Executive Dashboard</h3><div class="opacity-75">บูรณาการงบทดลอง การเงินรายเดือน HIS OPD/IPD และผลิตภาพเตียง เพื่อการตัดสินใจของผู้บริหาร</div></div>
  <div class="col-lg-3"><label class="small fw-bold mb-1">รูปแบบการประมวลผล</label><select id="filterPeriodMode" class="form-select form-select-lg fw-bold"><option value="month">รายเดือน</option><option value="quarter">สะสมรายไตรมาส</option><option value="fiscal" selected>สะสมปีงบประมาณ</option></select></div>
  <div class="col-lg-3"><label class="small fw-bold mb-1">เดือนสิ้นสุดรายงาน</label><input type="month" id="filterMonth" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($currentMonth, ENT_QUOTES, 'UTF-8') ?>"></div>
 </div>
</section>
<section class="period-strip">
 <div class="d-flex justify-content-between align-items-center mb-3">
  <div><div class="fw-bold text-dark"><i class="bi bi-calendar-range text-primary"></i> ช่วงข้อมูลที่ใช้วิเคราะห์</div><div class="small-note" id="analysisHeadline">กำลังตรวจสอบความเป็นปัจจุบันของข้อมูล...</div></div>
  <span class="pill pending" id="overallFreshness">กำลังโหลด</span>
 </div>
 <div class="row g-3" id="analysisPeriods"></div>
</section>

<div class="section-title">Executive Financial Snapshot</div>
<div class="row g-3">
 <div class="col-xl-3 col-md-6"><div class="panel summary-card" style="background:linear-gradient(135deg,#0f766e,#14b8a6)"><div class="summary-label">รายได้ตามช่วงวิเคราะห์</div><div class="summary-value" id="sumIncome">0</div><div class="small opacity-75">Operating revenue</div><i class="bi bi-cash-stack"></i></div></div>
 <div class="col-xl-3 col-md-6"><div class="panel summary-card" style="background:linear-gradient(135deg,#dc2626,#f97316)"><div class="summary-label">ค่าใช้จ่ายตามช่วงวิเคราะห์</div><div class="summary-value" id="sumExpense">0</div><div class="small opacity-75">Operating cost</div><i class="bi bi-receipt"></i></div></div>
 <div class="col-xl-3 col-md-6"><div class="panel summary-card" style="background:linear-gradient(135deg,#2563eb,#06b6d4)"><div class="summary-label">กำไร/ขาดทุนสุทธิ</div><div class="summary-value" id="sumProfit">0</div><div class="small opacity-75">ก่อนปรับรายการทางบัญชีเพิ่มเติม</div><i class="bi bi-graph-up-arrow"></i></div></div>
 <div class="col-xl-3 col-md-6"><div class="panel summary-card" style="background:linear-gradient(135deg,#7c3aed,#4f46e5)"><div class="summary-label">เงินสดและเงินฝาก</div><div class="summary-value" id="sumCash">0</div><div class="small opacity-75">จากงบทดลองล่าสุด</div><i class="bi bi-bank"></i></div></div>
</div>

<div class="section-title">TPS 1.2 ระยะเวลาบริหารเงินทุนหมุนเวียน</div>
<div class="row g-3" id="tpsGrid"></div>

<div class="section-title">TPS 1.3 การบริหารจัดการต้นทุนและผลิตภาพ</div>
<div class="row g-3" id="managementGrid"></div>

<div class="section-title">มิติ 2 Outcome ผลลัพธ์ทางการเงิน</div>
<div class="panel table-responsive">
 <table class="table spec-table mb-0"><thead><tr><th>รหัส</th><th>ตัวชี้วัด</th><th class="text-end">ผลลัพธ์</th><th>เกณฑ์ TPS</th><th>สถานะ</th><th>แหล่งข้อมูล</th></tr></thead><tbody id="outcomeRows"></tbody></table>
</div>

<div class="section-title">Integrated Analytics</div>
<div class="row g-4">
 <div class="col-xl-8"><div class="panel"><div class="fw-bold mb-3"><i class="bi bi-graph-up text-success"></i> แนวโน้มรายได้ ค่าใช้จ่าย และผลลัพธ์</div><div class="chart-box"><canvas id="financeTrend"></canvas></div></div></div>
 <div class="col-xl-4"><div class="panel"><div class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-primary"></i> โครงสร้างต้นทุน LC / MC / CC</div><div class="chart-box"><canvas id="costChart"></canvas></div></div></div>
</div>

<div class="section-title">ผลิตภาพเตียง แยกฐาน 93 + 9 เตียง</div>
<div class="row g-3" id="bedGrid"></div>

<div class="section-title">Data Integration & Governance</div>
<div class="row g-4">
 <div class="col-xl-5"><div class="panel"><div class="fw-bold mb-2"><i class="bi bi-database-check text-success"></i> ความพร้อมของแหล่งข้อมูล</div><div id="readinessList"></div></div></div>
 <div class="col-xl-7"><div class="panel table-responsive"><table class="table spec-table mb-0"><thead><tr><th>ชุดข้อมูล</th><th>ใช้คำนวณ</th><th>เจ้าของข้อมูล</th><th>ความถี่</th></tr></thead><tbody>
 <tr><td>Finance Monthly</td><td>รายได้ ค่าใช้จ่าย LC/MC และ Unit Cost</td><td>การเงิน/บัญชี/พัสดุ</td><td>รายเดือน</td></tr>
 <tr><td>Trial Balance</td><td>AR, Cash, Assets, Liabilities, Depreciation</td><td>บัญชี</td><td>ทุกสิ้นเดือน</td></tr>
 <tr><td>HIS OPD/IPD</td><td>Visit, Patient Days, Occupancy, Sum AdjRW</td><td>IT/เวชระเบียน</td><td>Real-time</td></tr>
 <tr><td>Costing Software</td><td>Benchmark Unit Cost และ Median กลุ่ม</td><td>การเงิน/กลุ่มงานยุทธศาสตร์</td><td>รอเชื่อมต่อ</td></tr>
 </tbody></table></div></div>
</div>
<div class="alert alert-warning mt-4 mb-0 small" id="dataNote">กำลังประมวลผลข้อมูล...</div>
</main>
<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
let financeTrendChart=null,costStructureChart=null;
document.addEventListener('DOMContentLoaded',()=>{loadCfo();document.getElementById('filterMonth').addEventListener('change',loadCfo);document.getElementById('filterPeriodMode').addEventListener('change',loadCfo)});
async function loadCfo(){try{const m=document.getElementById('filterMonth').value,mode=document.getElementById('filterPeriodMode').value,r=await fetch(`../../api/get_cfo_dashboard.php?month=${encodeURIComponent(m)}&period_mode=${encodeURIComponent(mode)}`,{credentials:'same-origin'}),d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'โหลดข้อมูลไม่สำเร็จ');renderAnalysisPeriods(d.analysis_periods||[],d.period||{});renderSummary(d.summary||{});renderTps(d.tps||{});renderMetrics('managementGrid',d.management||[]);renderOutcomes(d.outcomes||[]);renderBeds(d.beds||{});renderReadiness(d.data_readiness||[]);renderCharts(d.charts||{});document.getElementById('dataNote').textContent=`ช่วงประมวลผล ${d.period.label} ${d.period.analysis_start} ถึง ${d.period.report_end} · ปีงบประมาณ ${d.period.fiscal_year} · N = ${d.period.days_elapsed} วัน · ${(d.notes||[]).join(' · ')}`}catch(e){Swal.fire('เกิดข้อผิดพลาด',e.message,'error')}}
function renderAnalysisPeriods(rows,p){const labels={current:'ปัจจุบัน',watch:'ล่าช้า 1 เดือน',stale:'ข้อมูลล่าช้า',missing:'ยังไม่มีข้อมูล'},icons={current:'bi-check-circle-fill',watch:'bi-clock-fill',stale:'bi-exclamation-triangle-fill',missing:'bi-x-circle-fill'};document.getElementById('analysisPeriods').innerHTML=rows.map(x=>`<div class="col-xl-4 col-md-6"><div class="period-item ${x.status}"><div class="d-flex justify-content-between gap-2"><div class="fw-bold text-dark">${esc(x.name)}</div><span class="period-status">${labels[x.status]||x.status}</span></div><div class="period-range mt-2"><i class="bi ${icons[x.status]||'bi-calendar'}"></i> ${periodRange(x)}</div><div class="small-note mt-1">${esc(x.detail||'')}</div>${x.record_start&&x.record_end&&(x.start!==x.record_start||x.end!==x.record_end)?`<div class="small-note mt-1">มีรายการเดือน ${esc(x.record_start)} ถึง ${esc(x.record_end)} แต่บางเดือนยังไม่มีตัวเลข</div>`:''}</div></div>`).join('');const stale=rows.filter(x=>x.status==='stale'||x.status==='missing').length,watch=rows.filter(x=>x.status==='watch').length,badge=document.getElementById('overallFreshness');badge.className=`pill ${stale?'bad':watch?'pending':'good'}`;badge.textContent=stale?`มี ${stale} แหล่งข้อมูลที่ต้องติดตาม`:watch?`มี ${watch} แหล่งข้อมูลล่าช้า`:'ข้อมูลหลักเป็นปัจจุบัน';document.getElementById('analysisHeadline').textContent=`${p.label||'ช่วงวิเคราะห์'} ${p.analysis_start||'-'} ถึง ${p.report_end||'-'} · เดือนสิ้นสุด ${p.month||'-'} · ปีงบประมาณ ${p.fiscal_year||'-'}`}
function periodRange(x){if(!x.start&&!x.end)return'ยังไม่มีช่วงข้อมูล';if(x.start===x.end)return esc(x.start);return `${esc(x.start||'-')} ถึง ${esc(x.end||'-')}`}
function renderSummary(s){set('sumIncome',money(s.income));set('sumExpense',money(s.expense));set('sumProfit',s.net_income===null||typeof s.net_income==='undefined'?'รอข้อมูล':money(s.net_income));set('sumCash',money(s.cash))}
function renderTps(t){const rows=[{...t.ap,name:'AP Days',formula:'เจ้าหนี้คงค้าง ÷ (ยอดจัดซื้อสะสม ÷ N)',unit:'วัน'},{...t.ar_uc,name:'AR UC Days',formula:'ลูกหนี้ UC ÷ (รายได้ UC สะสม ÷ N)',unit:'วัน'},{...t.ar_csmbs,name:'AR CSMBS Days',formula:'ลูกหนี้ข้าราชการ ÷ (รายได้สะสม ÷ N)',unit:'วัน'},{...t.inventory,name:'Inventory Days',formula:'สินค้าคงคลัง ÷ (ยอดเบิกใช้สะสม ÷ N)',unit:'วัน'}];renderMetrics('tpsGrid',rows)}
function renderMetrics(id,rows){document.getElementById(id).innerHTML=rows.map(x=>`<div class="col-xl-3 col-md-6"><article class="panel metric-card ${x.status||'pending'}"><div class="metric-code">${esc(x.code||'-')} · ${esc(x.source||'Integrated Data')}</div><div class="metric-name">${esc(x.name||'-')}</div><div class="metric-value">${metricValue(x)}</div><span class="pill ${x.status||'pending'}">${statusText(x)}</span><div class="formula">${esc(x.formula||'-')}</div></article></div>`).join('')}
function renderOutcomes(rows){document.getElementById('outcomeRows').innerHTML=rows.map(x=>`<tr><td class="fw-bold text-info">${esc(x.code)}</td><td class="fw-bold">${esc(x.name)}<div class="small-note">${esc(x.formula)}</div></td><td class="text-end fw-bold">${metricValue(x)}</td><td>${targetText(x)}</td><td><span class="pill ${x.status}">${statusText(x)}</span></td><td>${esc(x.source)}</td></tr>`).join('')}
function renderBeds(b){const rows=[['ทั่วไป',b.general,'#2563eb','ฐาน 93 เตียง ไม่รวม ICU'],['ICU / กึ่งวิกฤต',b.icu,'#dc2626','ฐาน 9 เตียง'],['รวมทั้งระบบ',b.total,'#0f766e','ฐานรวม 102 เตียง']];document.getElementById('bedGrid').innerHTML=rows.map(([n,x,c,sub])=>`<div class="col-xl-4"><div class="panel" style="border-top:5px solid ${c}"><div class="fw-bold" style="color:${c}">${n}</div><div class="metric-value">${num(x.active)} / ${num(x.beds)}</div><div class="small-note mb-3">${sub} · Patient Days ${num(x.patient_days)}</div><div class="bed-track"><div class="bed-fill" style="width:${Math.min(Number(x.occupancy||0),100)}%;background:${c}"></div></div><div class="d-flex justify-content-between small mt-2"><span>Occupancy <b>${num(x.occupancy,2)}%</b></span><span>ว่าง <b>${num(x.available)}</b></span></div></div></div>`).join('')}
function renderReadiness(rows){document.getElementById('readinessList').innerHTML=rows.map(x=>`<div class="readiness"><div class="readiness-icon ${x.ready?'':'off'}"><i class="bi ${x.ready?'bi-check-lg':'bi-hourglass-split'}"></i></div><div><div class="fw-bold">${esc(x.name)}</div><div class="small-note">${esc(x.detail)}</div></div><span class="pill ${x.ready?'good':'pending'} ms-auto">${x.ready?'พร้อมใช้':'รอข้อมูล'}</span></div>`).join('')}
function renderCharts(c){if(financeTrendChart)financeTrendChart.destroy();financeTrendChart=new Chart(document.getElementById('financeTrend'),{type:'bar',data:{labels:c.labels||[],datasets:[{label:'รายได้',data:c.income||[],backgroundColor:'#0f766e',borderRadius:6},{label:'ค่าใช้จ่าย',data:c.expense||[],backgroundColor:'#dc2626',borderRadius:6},{type:'line',label:'กำไร/ขาดทุน',data:c.profit||[],borderColor:'#2563eb',backgroundColor:'#2563eb',borderWidth:3,tension:.3}]},options:chartOptions()});if(costStructureChart)costStructureChart.destroy();costStructureChart=new Chart(document.getElementById('costChart'),{type:'doughnut',data:{labels:c.cost_structure?.labels||[],datasets:[{data:c.cost_structure?.values||[],backgroundColor:['#7c3aed','#2563eb','#06b6d4','#f97316','#64748b'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'58%',plugins:{legend:{position:'bottom'}}}})}
function chartOptions(){return{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{callback:v=>shortMoney(v)}}}}}
function metricValue(x){if(x.value===null||typeof x.value==='undefined')return'รอข้อมูล';return x.unit==='บาท'?money(x.value):`${num(x.value,2)} ${esc(x.unit||'')}`}
function targetText(x){if(x.target===null||typeof x.target==='undefined')return'รอ Benchmark';return `${x.mode==='min'?'≥':'≤'} ${num(x.target,2)} ${esc(x.unit||'')}`}
function statusText(x){return x.status==='good'?'ผ่านเกณฑ์':x.status==='bad'?'ต้องปรับปรุง':'รอ Benchmark/ข้อมูล'}
function set(id,v){document.getElementById(id).textContent=v}function money(v){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2})}function shortMoney(v){v=Number(v||0);return Math.abs(v)>=1e6?`${num(v/1e6,1)}ล.`:Math.abs(v)>=1e3?`${num(v/1e3)}พ.`:num(v)}function num(v,d=0){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:d,maximumFractionDigits:d})}function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
</script>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_login();
$currentMonth = date('Y-m');
?>
<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>
<style>
.content{margin-left:260px;padding:30px;min-height:100vh;background:#f3f6fb}.hero,.panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 5px 16px rgba(15,23,42,.05)}.hero{padding:24px;background:linear-gradient(125deg,#0f172a,#0f766e);color:#fff}.panel{padding:20px}.metric{height:100%;padding:16px;border-radius:14px;background:#f8fafc;border-left:5px solid #0f766e}.metric-value{font-size:27px;font-weight:800;color:#0f172a}.mapping-table{font-size:12px}.mapping-table th{white-space:nowrap;position:sticky;top:0;background:#f1f5f9;z-index:2}.mapping-table td{vertical-align:middle}.mapping-wrap{max-height:640px;overflow:auto}.tag-check{width:18px;height:18px}.form-label{font-size:12px;font-weight:800;color:#475569}.section-title{margin:26px 0 14px;padding-left:12px;border-left:5px solid #0f766e;font-size:19px;font-weight:800}.mini-form{height:100%;padding:18px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.flag-label{display:block;font-weight:800;color:#0f172a}.flag-code{display:block;font-size:10px;color:#64748b}.flag-meta{font-size:9px;font-weight:800;border-radius:999px;padding:2px 5px;background:#e0f2fe;color:#075985}.mapping-help{font-size:11px;color:#64748b;line-height:1.25}.mapping-select{min-width:190px}.statement-card{border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;padding:16px}.statement-status{border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800}.statement-status.matched{background:#dcfce7;color:#166534}.statement-status.pdf_only{background:#ffedd5;color:#9a3412}.statement-status.missing_pdf{background:#fee2e2;color:#991b1b}@media(max-width:991px){.content{margin-left:0;padding:18px}}
</style>
<main class="content">
<section class="hero mb-4">
 <div class="row align-items-center g-3">
  <div class="col-lg-7"><div class="small fw-bold opacity-75">FINANCE DATA GOVERNANCE</div><h3 class="fw-bold mb-1"><i class="bi bi-diagram-3-fill"></i> Account Mapping & Management Data</h3><div class="opacity-75">จัดหมวดรหัสบัญชี ตรวจความพร้อม CFO และบันทึกข้อมูลที่ไม่มีในงบทดลอง</div></div>
  <div class="col-lg-3"><label class="small fw-bold">เดือนข้อมูล</label><input type="month" id="monthFilter" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($currentMonth, ENT_QUOTES, 'UTF-8') ?>"></div>
  <div class="col-lg-2 d-grid"><label class="small fw-bold">&nbsp;</label><button class="btn btn-light btn-lg fw-bold" onclick="recalculate()"><i class="bi bi-arrow-repeat"></i> คำนวณใหม่</button></div>
 </div>
</section>

<div class="row g-3 mb-4">
 <div class="col-xl-3 col-md-6"><div class="metric"><div class="small text-secondary">บัญชีทั้งหมด</div><div class="metric-value" id="totalAccounts">0</div></div></div>
 <div class="col-xl-3 col-md-6"><div class="metric"><div class="small text-secondary">มี Mapping แล้ว</div><div class="metric-value" id="mappedAccounts">0</div></div></div>
 <div class="col-xl-3 col-md-6"><div class="metric"><div class="small text-secondary">ตรวจทานแล้ว</div><div class="metric-value" id="reviewedAccounts">0</div></div></div>
 <div class="col-xl-3 col-md-6"><div class="metric"><div class="small text-secondary">ความพร้อมสูตร CFO</div><div class="metric-value" id="readinessPercent">0%</div></div></div>
</div>

<section class="panel mb-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
 <div><h5 class="fw-bold mb-1">ตาราง Mapping รหัสบัญชี</h5><div class="small text-secondary" id="auditNote">กำลังโหลดผลตรวจ...</div></div>
  <input type="search" id="mappingSearch" class="form-control" style="max-width:320px" placeholder="ค้นหารหัสหรือชื่อบัญชี">
 </div>
 <div class="alert alert-info small mb-3">
  <b>แนวทางจับคู่:</b>
  รายได้ให้เลือก <b>รายได้</b> และใช้ <b>เครดิตเดือนนี้</b>,
  ค่าใช้จ่ายให้ใช้ <b>เดบิตเดือนนี้</b>,
  เงินสด/ลูกหนี้/คลังให้ใช้ <b>เดบิตสุทธิ</b>,
  เจ้าหนี้ให้ใช้ <b>เครดิตสุทธิ</b>.
  ช่อง “นำไปช่อง” คือปลายทางที่ระบบจะเติมเข้า CFO Dashboard อัตโนมัติ
 </div>
 <div class="statement-card mb-3">
  <div class="row g-3 align-items-end">
   <div class="col-lg-4">
    <h6 class="fw-bold mb-1"><i class="bi bi-files text-primary"></i> ชุดไฟล์ CFO รายเดือน</h6>
    <div class="small text-secondary">Excel งบทดลองฉบับเต็มใช้คำนวณ/Mapping ส่วน PDF เป็นสรุปอ้างอิงสำหรับผู้บริหาร</div>
   </div>
   <div class="col-lg-4">
    <form id="trialBalanceExcelForm" enctype="multipart/form-data">
     <input type="hidden" name="action" value="upload_trial_balance_excel_document">
     <input type="hidden" name="month_year">
     <label class="form-label">Excel งบทดลองฉบับเต็ม</label>
     <input class="form-control" type="file" name="trial_balance_excel" accept=".xls,.xlsx" required>
    </form>
   </div>
   <div class="col-lg-4">
    <form id="statementPdfForm" enctype="multipart/form-data">
     <input type="hidden" name="action" value="upload_statement_pdf">
     <input type="hidden" name="month_year">
     <label class="form-label">PDF สรุปจากงบทดลอง</label>
     <input class="form-control" type="file" name="statement_pdf" accept="application/pdf,.pdf" required>
    </form>
   </div>
   <div class="col-lg-12">
    <div class="d-flex flex-wrap gap-2 justify-content-end">
     <button class="btn btn-primary fw-bold" form="trialBalanceExcelForm"><i class="bi bi-file-earmark-spreadsheet"></i> เก็บ Excel ต้นฉบับ</button>
     <button class="btn btn-danger fw-bold" form="statementPdfForm"><i class="bi bi-file-earmark-pdf"></i> เก็บ PDF สรุป</button>
    </div>
   </div>
  </div>
  <div class="mt-3" id="statementDocumentList">
   <div class="small text-muted">กำลังตรวจเอกสาร PDF...</div>
  </div>
 </div>
 <div class="mapping-wrap"><table class="table table-hover mapping-table"><thead><tr id="mappingHeader"></tr></thead><tbody id="mappingRows"><tr><td colspan="28" class="text-center py-4">กำลังโหลด...</td></tr></tbody></table></div>
</section>

<div class="section-title">ข้อมูลบริหารเสริมที่ไม่มีในงบทดลอง</div>
<div class="row g-3">
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_planfin"><h6 class="fw-bold"><i class="bi bi-bullseye text-primary"></i> PlanFin / งบประมาณ</h6><input type="hidden" name="month_year"><label class="form-label">เป้ารายได้</label><input class="form-control mb-2" name="revenue_target" type="number" step="0.01"><label class="form-label">งบค่าใช้จ่าย</label><input class="form-control mb-2" name="expense_budget" type="number" step="0.01"><label class="form-label">งบลงทุน</label><input class="form-control mb-3" name="investment_budget" type="number" step="0.01"><button class="btn btn-primary w-100">บันทึก PlanFin</button></form></div>
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_aging"><h6 class="fw-bold"><i class="bi bi-hourglass-split text-warning"></i> AR / AP Aging</h6><input type="hidden" name="month_year"><label class="form-label">ประเภท</label><select class="form-select mb-2" name="aging_type"><option value="AR_UC">ลูกหนี้ UC</option><option value="AR_CSMBS">ลูกหนี้ข้าราชการ</option><option value="AR_OTHER">ลูกหนี้อื่น</option><option value="AP">เจ้าหนี้</option></select><div class="row g-2"><div class="col-6"><label class="form-label">0-30 วัน</label><input class="form-control" name="0-30" type="number" step="0.01"></div><div class="col-6"><label class="form-label">31-60 วัน</label><input class="form-control" name="31-60" type="number" step="0.01"></div><div class="col-6"><label class="form-label">61-90 วัน</label><input class="form-control" name="61-90" type="number" step="0.01"></div><div class="col-6"><label class="form-label">&gt; 90 วัน</label><input class="form-control" name="OVER_90" type="number" step="0.01"></div></div><button class="btn btn-warning w-100 mt-3">บันทึก Aging</button></form></div>
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_claim"><h6 class="fw-bold"><i class="bi bi-file-medical text-danger"></i> Claim Quality</h6><input type="hidden" name="month_year"><label class="form-label">จำนวน Claim</label><input class="form-control mb-2" name="claim_count" type="number"><label class="form-label">Claim Lag เฉลี่ย (วัน)</label><input class="form-control mb-2" name="claim_lag_days" type="number" step="0.01"><label class="form-label">จำนวนปฏิเสธ</label><input class="form-control mb-2" name="denial_count" type="number"><label class="form-label">Denial Rate (%)</label><input class="form-control mb-3" name="denial_rate" type="number" step="0.01"><button class="btn btn-danger w-100">บันทึก Claim</button></form></div>
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_cost_center"><h6 class="fw-bold"><i class="bi bi-building-gear text-info"></i> Cost Center</h6><input type="hidden" name="month_year"><input class="form-control mb-2" name="cost_center_code" placeholder="รหัสหน่วยต้นทุน" required><input class="form-control mb-2" name="cost_center_name" placeholder="ชื่อหน่วยต้นทุน" required><select class="form-select mb-2" name="service_type"><option value="OP">ผู้ป่วยนอก (OP)</option><option value="IP">ผู้ป่วยใน (IP)</option><option value="SHARED" selected>ต้นทุนร่วม (Shared)</option></select><div class="row g-2"><div class="col-4"><input class="form-control" name="lc_cost" placeholder="ค่าแรง LC" type="number" step="0.01"></div><div class="col-4"><input class="form-control" name="mc_cost" placeholder="ค่าวัสดุ MC" type="number" step="0.01"></div><div class="col-4"><input class="form-control" name="cc_cost" placeholder="ค่าใช้จ่ายอื่น CC" type="number" step="0.01"></div></div><button class="btn btn-info w-100 mt-3">บันทึก Cost Center</button></form></div>
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_inventory_usage"><h6 class="fw-bold"><i class="bi bi-box-seam text-success"></i> ยอดเบิกใช้คลังจริง</h6><input type="hidden" name="month_year"><select class="form-select mb-2" name="inventory_type"><option value="DRUG">ยา</option><option value="MEDICAL">เวชภัณฑ์/วัสดุการแพทย์</option><option value="SCIENCE">วัสดุวิทยาศาสตร์/Lab</option><option value="GENERAL">วัสดุทั่วไป</option></select><input class="form-control mb-2" name="beginning_balance" placeholder="ยอดต้นงวด" type="number" step="0.01"><input class="form-control mb-2" name="purchases" placeholder="รับเข้า/จัดซื้อ" type="number" step="0.01"><input class="form-control mb-2" name="actual_issues" placeholder="เบิกใช้จริง" type="number" step="0.01"><input class="form-control mb-3" name="ending_balance" placeholder="ยอดปลายงวด" type="number" step="0.01"><button class="btn btn-success w-100">บันทึกยอดเบิกใช้</button></form></div>
 <div class="col-xl-4"><form class="mini-form governance-form" data-action="save_asset"><h6 class="fw-bold"><i class="bi bi-pc-display text-secondary"></i> ทะเบียนสินทรัพย์</h6><input class="form-control mb-2" name="asset_code" placeholder="รหัสสินทรัพย์" required><input class="form-control mb-2" name="asset_name" placeholder="ชื่อสินทรัพย์" required><input class="form-control mb-2" name="asset_group" placeholder="กลุ่มสินทรัพย์"><input class="form-control mb-2" name="cost_center_code" placeholder="Cost Center"><input class="form-control mb-2" name="acquisition_date" type="date"><input class="form-control mb-2" name="acquisition_cost" placeholder="ราคาทุน" type="number" step="0.01"><input class="form-control mb-2" name="accumulated_depreciation" placeholder="ค่าเสื่อมสะสม" type="number" step="0.01"><input class="form-control mb-3" name="monthly_depreciation" placeholder="ค่าเสื่อมรายเดือน" type="number" step="0.01"><button class="btn btn-secondary w-100">บันทึกสินทรัพย์</button></form></div>
</div>
</main>
<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
const flags=[
 'is_cash','is_ar_uc','is_ar_csmbs','is_ar_sss','is_ar_other','is_ap','is_inventory',
 'is_current_asset','is_fixed_asset','is_current_liability','is_longterm_liability','is_equity_fund',
 'is_revenue','is_revenue_operating','is_revenue_non_operating','is_lc','is_mc','is_cc',
 'is_depreciation','is_finance_cost','is_project_grant','is_op','is_ip'
];
const flagMeta={
 is_cash:{label:'เงินสด',code:'Cash'},
 is_ar_uc:{label:'ลูกหนี้ UC',code:'AR UC'},
 is_ar_csmbs:{label:'ลูกหนี้ข้าราชการ',code:'AR CSMBS'},
 is_ar_sss:{label:'ลูกหนี้ประกันสังคม',code:'AR SSS'},
 is_ar_other:{label:'ลูกหนี้อื่น',code:'AR Other'},
 is_ap:{label:'เจ้าหนี้',code:'AP'},
 is_inventory:{label:'สินค้าคงคลัง',code:'Inventory'},
 is_current_asset:{label:'สินทรัพย์หมุนเวียน',code:'CA'},
 is_fixed_asset:{label:'สินทรัพย์ไม่หมุนเวียน',code:'FA'},
 is_current_liability:{label:'หนี้สินหมุนเวียน',code:'CL'},
 is_longterm_liability:{label:'หนี้สินระยะยาว',code:'LTL'},
 is_equity_fund:{label:'ทุน/กองทุน',code:'Fund'},
 is_revenue:{label:'รายได้',code:'Revenue'},
 is_revenue_operating:{label:'รายได้ดำเนินงาน',code:'Op Rev'},
 is_revenue_non_operating:{label:'รายได้อื่น/นอกดำเนินงาน',code:'Other Rev'},
 is_lc:{label:'ค่าแรง',code:'LC'},
 is_mc:{label:'ค่าวัสดุ',code:'MC'},
 is_cc:{label:'ค่าใช้จ่ายอื่น',code:'CC'},
 is_depreciation:{label:'ค่าเสื่อมราคา',code:'Dep'},
 is_finance_cost:{label:'ต้นทุนการเงิน',code:'Fin Cost'},
 is_project_grant:{label:'เงินโครงการ/เงินอุดหนุน',code:'Grant'},
 is_op:{label:'ผู้ป่วยนอก',code:'OP'},
 is_ip:{label:'ผู้ป่วยใน',code:'IP'}
};
const autoFields=['','treatment_income','drug_income','lab_income','water_bill','electric_bill','compensation','maintenance_fund','inv_drug_value','inv_medical_supply','inv_science_material'];
const flagLabels=Object.fromEntries(Object.entries(flagMeta).map(([key,value])=>[key,value.label]));
const autoFieldLabels={
 '':'ไม่เชื่อมช่องอัตโนมัติ (ใช้ tag บัญชีแทน)',
 treatment_income:'รายได้ค่ารักษา',
 drug_income:'รายได้ค่ายา',
 lab_income:'รายได้ Lab/X-ray',
 water_bill:'ค่าน้ำประปา',
 electric_bill:'ค่าไฟฟ้า',
 compensation:'เงินเดือน/ค่าจ้าง/ค่าตอบแทน',
 maintenance_fund:'เงินสด/เงินบำรุงคงเหลือ',
 inv_drug_value:'มูลค่ายาคงคลัง',
 inv_medical_supply:'มูลค่าเวชภัณฑ์/วัสดุการแพทย์',
 inv_science_material:'มูลค่าวัสดุวิทยาศาสตร์/Lab'
};
const valueBasisLabels={
 month_debit:'ใช้เดบิตเดือนนี้',
 month_credit:'ใช้เครดิตเดือนนี้',
 net_debit:'ใช้เดบิตสุทธิ',
 net_credit:'ใช้เครดิตสุทธิ'
};
let mappings=[];
let statementDocuments=[];
let statementDocumentArchive=[];
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
document.addEventListener('DOMContentLoaded',()=>{renderMappingHeader();loadGovernance();document.getElementById('monthFilter').addEventListener('change',loadGovernance);document.getElementById('mappingSearch').addEventListener('input',renderMappings);document.getElementById('statementPdfForm').addEventListener('submit',uploadStatementPdf);document.getElementById('trialBalanceExcelForm').addEventListener('submit',uploadTrialBalanceExcel);document.querySelectorAll('.governance-form').forEach(f=>f.addEventListener('submit',saveSupplemental))});
function renderMappingHeader(){document.getElementById('mappingHeader').innerHTML=`<th>รหัส / ชื่อบัญชี</th>${flags.map(f=>`<th>${esc(flagMeta[f]?.label||f)}<br><span class="flag-code">${esc(flagMeta[f]?.code||f)}</span></th>`).join('')}<th>นำไปช่อง</th><th>ใช้ยอดจาก</th><th>ตรวจแล้ว</th><th></th>`}
async function api(payload){const r=await fetch('../../api/finance_governance.php',{method:payload?'POST':'GET',credentials:'same-origin',headers:payload?{'Content-Type':'application/json'}:{},body:payload?JSON.stringify(payload):null});const d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'ดำเนินการไม่สำเร็จ');return d}
async function loadGovernance(){try{const m=document.getElementById('monthFilter').value,r=await fetch(`../../api/finance_governance.php?month=${encodeURIComponent(m)}`,{credentials:'same-origin'}),d=await r.json();if(!r.ok)throw new Error(d.message);mappings=d.mappings||[];statementDocuments=d.statement_documents||[];statementDocumentArchive=d.statement_document_archive||[];const a=d.audit||{};set('totalAccounts',a.total_accounts||0);set('mappedAccounts',a.mapped_accounts||0);set('reviewedAccounts',a.reviewed_accounts||0);set('readinessPercent',`${Number(a.readiness_percent||0).toFixed(2)}%`);set('auditNote',`บัญชีใหม่ ${a.new_codes?.length||0} · บัญชีหายจากเดือนก่อน ${a.missing_codes?.length||0} · ยังไม่มี Mapping ${a.unmapped_codes?.length||0}`);document.querySelectorAll('[name=month_year]').forEach(x=>x.value=m);renderStatementDocuments();renderMappings()}catch(e){Swal.fire('โหลดข้อมูลไม่สำเร็จ',e.message,'error')}}
function statementStatusText(v){return {matched:'ครบไฟล์ + งบทดลอง',pdf_only:'มีไฟล์ แต่ยังไม่พบงบทดลอง',missing_pdf:'ยังไม่มีไฟล์'}[v]||v}
function documentTypeText(v){return {monthly_statement:'PDF สรุปจากงบทดลอง',trial_balance_excel:'Excel งบทดลองฉบับเต็ม'}[v]||v}
function jsonCount(v){try{return Array.isArray(JSON.parse(v||'[]'))?JSON.parse(v||'[]').length:0}catch(e){return 0}}
function extractText(x){if(x.document_type!=='monthly_statement')return '';const cls=x.extract_status==='success'?'bg-success':(x.extract_status==='failed'?'bg-danger':'bg-warning text-dark');const metrics=jsonCount(x.metrics_json),checks=jsonCount(x.reconcile_json);return `<span class="badge ${cls} ms-1">PDF Extract ${esc(x.extract_status||'pending')}</span><span class="badge bg-info text-dark ms-1">metrics ${metrics}</span><span class="badge bg-light text-dark border ms-1">reconcile ${checks}</span>`}
function statementDocumentRow(x,compact=false){return `<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white border rounded-3 p-3 mb-2"><div><div class="fw-bold">${esc(x.month_year)} · ${esc(documentTypeText(x.document_type))}: ${esc(x.original_filename)}</div><div class="small text-secondary">${Number(x.file_size||0).toLocaleString('th-TH')} bytes · งบทดลองในระบบ ${Number(x.trial_balance_rows||0).toLocaleString('th-TH')} บัญชี${x.uploaded_at?` · อัปโหลด ${esc(x.uploaded_at)}`:''}</div>${compact?'':`<div class="small mt-1">${extractText(x)}</div>`}</div><div class="d-flex align-items-center gap-2"><span class="statement-status ${esc(x.match_status)}">${esc(statementStatusText(x.match_status))}</span><a class="btn btn-sm btn-outline-primary" href="../../${esc(x.relative_path)}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> เปิดไฟล์</a></div></div>`}
function renderStatementDocuments(){const box=document.getElementById('statementDocumentList'),m=document.getElementById('monthFilter').value,hasPdf=statementDocuments.some(x=>x.document_type==='monthly_statement'),hasExcel=statementDocuments.some(x=>x.document_type==='trial_balance_excel'),archive=statementDocumentArchive.filter(x=>x.month_year!==m);const selectedSummary=`<div class="small mb-2"><span class="badge ${hasExcel?'bg-success':'bg-warning text-dark'} me-1">Excel เดือนนี้ ${hasExcel?'มีแล้ว':'ยังไม่มี'}</span><span class="badge ${hasPdf?'bg-success':'bg-warning text-dark'}">PDF เดือนนี้ ${hasPdf?'มีแล้ว':'ยังไม่มี'}</span>${(hasExcel||hasPdf)?'<span class="badge bg-danger ms-1">อัปโหลดซ้ำจะทับไฟล์เดิมของเดือนนี้</span>':''}</div>`;const selectedBlock=statementDocuments.length?statementDocuments.map(x=>statementDocumentRow(x)).join(''):'<div class="alert alert-warning small mb-3"><i class="bi bi-exclamation-triangle"></i> ยังไม่มีชุดไฟล์ CFO สำหรับเดือนที่เลือก ให้ตรวจคลังไฟล์ด้านล่างก่อนอัปโหลด</div>';const archiveBlock=archive.length?`<div class="fw-bold small mt-3 mb-2"><i class="bi bi-archive"></i> ไฟล์รายเดือนที่เก็บอยู่ในระบบล่าสุด ${archive.length} รายการ</div>${archive.map(x=>statementDocumentRow(x,true)).join('')}`:'<div class="alert alert-light border small mb-0"><i class="bi bi-info-circle"></i> ยังไม่มีไฟล์เดือนอื่นในระบบ</div>';box.innerHTML=selectedSummary+selectedBlock+archiveBlock}
async function confirmOverwriteDocument(type){const m=document.getElementById('monthFilter').value,existing=statementDocumentArchive.find(x=>x.month_year===m&&x.document_type===type);if(!existing)return '0';const result=await Swal.fire({title:'พบไฟล์เดือนนี้อยู่แล้ว',html:`${esc(documentTypeText(type))}<br><b>${esc(existing.original_filename)}</b><br>ถ้าอัปโหลดต่อ ระบบจะบันทึกทับรายการเดิมของเดือน ${esc(m)}`,icon:'warning',showCancelButton:true,confirmButtonText:'อัปโหลดทับ',cancelButtonText:'ยกเลิก'});return result.isConfirmed?'1':false}
async function uploadStatementPdf(e){e.preventDefault();const form=e.currentTarget,fd=new FormData(form);try{const overwrite=await confirmOverwriteDocument('monthly_statement');if(overwrite===false)return;if(overwrite==='1')fd.append('force_overwrite','1');Swal.fire({title:'กำลังอัปโหลด PDF...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});const r=await fetch('../../api/finance_governance.php',{method:'POST',credentials:'same-origin',body:fd}),d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'อัปโหลดไม่สำเร็จ');if(d.month)document.getElementById('monthFilter').value=d.month;form.reset();await loadGovernance();Swal.fire('สำเร็จ',d.message,'success')}catch(err){Swal.fire('อัปโหลดไม่สำเร็จ',err.message,'error')}}
async function uploadTrialBalanceExcel(e){e.preventDefault();const form=e.currentTarget,fd=new FormData(form);try{const overwrite=await confirmOverwriteDocument('trial_balance_excel');if(overwrite===false)return;if(overwrite==='1')fd.append('force_overwrite','1');Swal.fire({title:'กำลังเก็บไฟล์ Excel...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});const r=await fetch('../../api/finance_governance.php',{method:'POST',credentials:'same-origin',body:fd}),d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'อัปโหลดไม่สำเร็จ');if(d.month)document.getElementById('monthFilter').value=d.month;form.reset();await loadGovernance();Swal.fire('สำเร็จ',d.message,'success')}catch(err){Swal.fire('อัปโหลดไม่สำเร็จ',err.message,'error')}}
function autoFieldText(v){return `${autoFieldLabels[v]||v}${v?` (${v})`:''}`}
function valueBasisText(v){return `${valueBasisLabels[v]||v} (${v})`}
function hasAny(x,keys){return keys.some(k=>Number(x[k]||0)>0)}
function mappingAdvice(x){
 if(hasAny(x,['is_cash','is_ar_uc','is_ar_csmbs','is_ar_sss','is_ar_other','is_inventory','is_current_asset','is_fixed_asset']))return 'งบดุลฝั่งสินทรัพย์: ใช้ tag + เดบิตสุทธิ และไม่ต้องเชื่อมช่องอัตโนมัติ';
 if(hasAny(x,['is_ap','is_current_liability','is_longterm_liability','is_equity_fund']))return 'งบดุลฝั่งหนี้สิน/ทุน: ใช้ tag + เครดิตสุทธิ และไม่ต้องเชื่อมช่องอัตโนมัติ';
 if(hasAny(x,['is_revenue','is_revenue_operating','is_revenue_non_operating','is_project_grant']))return 'รายได้: ถ้าต้องเติม Finance Monthly ให้เลือกช่องรายได้ที่ตรง และใช้เครดิตเดือนนี้';
 if(hasAny(x,['is_lc','is_mc','is_cc','is_depreciation','is_finance_cost']))return 'ค่าใช้จ่าย: ถ้าต้องเติม Finance Monthly ให้เลือกช่องค่าใช้จ่ายที่ตรง และใช้เดบิตเดือนนี้';
 return 'เลือก tag ให้ตรงหมวดบัญชี ส่วนช่องอัตโนมัติใช้เฉพาะรายการที่ต้องเติม Finance Monthly';
}
function renderMappings(){const q=document.getElementById('mappingSearch').value.toLowerCase(),rows=mappings.filter(x=>`${x.account_code} ${x.account_name}`.toLowerCase().includes(q));document.getElementById('mappingRows').innerHTML=rows.map(x=>`<tr data-code="${esc(x.account_code)}"><td><div class="fw-bold">${esc(x.account_code)}</div><div class="text-secondary">${esc(x.account_name)}</div><div class="mapping-help mt-1">${esc(mappingAdvice(x))}</div></td>${flags.map(f=>`<td class="text-center"><input class="tag-check" type="checkbox" data-field="${f}" title="${esc(flagLabels[f]||f)}" aria-label="${esc(flagLabels[f]||f)}" ${Number(x[f])?'checked':''}></td>`).join('')}<td><select class="form-select form-select-sm mapping-select" data-field="auto_field">${autoFields.map(v=>`<option value="${v}" ${x.auto_field===v?'selected':''}>${esc(autoFieldText(v))}</option>`).join('')}</select><div class="mapping-help">ใช้เฉพาะช่อง Finance Monthly เดิม เช่น รายได้ ค่าไฟ ค่าตอบแทน คลัง</div></td><td><select class="form-select form-select-sm mapping-select" data-field="value_basis">${['month_debit','month_credit','net_debit','net_credit'].map(v=>`<option value="${v}" ${x.value_basis===v?'selected':''}>${esc(valueBasisText(v))}</option>`).join('')}</select><div class="mapping-help">สินทรัพย์ใช้เดบิตสุทธิ หนี้สิน/ทุนใช้เครดิตสุทธิ รายได้ใช้เครดิตเดือนนี้ ค่าใช้จ่ายใช้เดบิตเดือนนี้</div></td><td class="text-center"><input class="tag-check" type="checkbox" data-field="is_reviewed" title="ตรวจสอบ Mapping แล้ว" aria-label="ตรวจสอบ Mapping แล้ว" ${Number(x.is_reviewed)?'checked':''}></td><td><button class="btn btn-sm btn-primary" onclick="saveMapping(this)">บันทึก</button></td></tr>`).join('')}
async function saveMapping(btn){const tr=btn.closest('tr'),payload={action:'save_mapping',account_code:tr.dataset.code};tr.querySelectorAll('[data-field]').forEach(x=>payload[x.dataset.field]=x.type==='checkbox'?x.checked:x.value);try{btn.disabled=true;const d=await api(payload);await loadGovernance();Swal.fire('สำเร็จ',d.message,'success')}catch(e){Swal.fire('บันทึกไม่สำเร็จ',e.message,'error')}finally{btn.disabled=false}}
async function recalculate(){try{const d=await api({action:'recalculate',month:document.getElementById('monthFilter').value});await loadGovernance();Swal.fire('คำนวณแล้ว',d.message,'success')}catch(e){Swal.fire('คำนวณไม่สำเร็จ',e.message,'error')}}
async function saveSupplemental(e){e.preventDefault();const form=e.currentTarget,payload={action:form.dataset.action};new FormData(form).forEach((v,k)=>payload[k]=v);try{const d=await api(payload);Swal.fire('สำเร็จ',d.message,'success')}catch(err){Swal.fire('บันทึกไม่สำเร็จ',err.message,'error')}}
function set(id,v){document.getElementById(id).textContent=Number.isFinite(Number(v))?Number(v).toLocaleString('th-TH'):v}
</script>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/invc_database.php';

$defaultFiscalYear = invc_current_fiscal_year();
?>
<?php include_once __DIR__ . '/../../layout/header.php'; ?>
<?php include_once __DIR__ . '/../../layout/sidebar.php'; ?>

<style>
.content{margin-left:260px;padding:30px;min-height:100vh;background:#f3f6fb}.hero,.panel,.metric-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 5px 16px rgba(15,23,42,.05)}.hero{padding:24px;background:linear-gradient(125deg,#0f172a,#0f766e);color:#fff}.metric-card{height:100%;padding:18px;border-left:5px solid #0f766e}.metric-card.blue{border-left-color:#2563eb}.metric-card.amber{border-left-color:#f59e0b}.metric-card.red{border-left-color:#dc2626}.metric-card.purple{border-left-color:#7c3aed}.metric-card.gray{border-left-color:#64748b}.metric-label{font-size:13px;font-weight:800;color:#64748b}.metric-value{font-size:28px;font-weight:900;color:#0f172a;line-height:1.15}.metric-sub{font-size:12px;color:#64748b}.panel{padding:22px}.chart-canvas{height:330px;position:relative}.progress-xl{height:20px;border-radius:999px;background:#e2e8f0;overflow:hidden}.progress-xl>div{height:100%;background:linear-gradient(90deg,#0f766e,#22c55e);border-radius:999px}.status-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:7px 12px;font-size:13px;font-weight:800}.status-ok{background:#dcfce7;color:#166534}.status-warn{background:#ffedd5;color:#9a3412}.no-data{border:1px dashed #94a3b8;background:#f8fafc;color:#475569;border-radius:18px;padding:32px;text-align:center}.small-note{font-size:12px;color:#64748b}.detail-row{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #eef2f7}.detail-row:last-child{border-bottom:0}.detail-label{font-size:13px;color:#64748b;font-weight:800}.detail-value{font-size:14px;color:#0f172a;font-weight:900;text-align:right}.sync-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.sync-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px}@media(max-width:991px){.content{margin-left:0;padding:18px}.sync-grid{grid-template-columns:1fr}}
</style>

<main class="content">
    <section class="hero mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <div class="small fw-bold opacity-75">SMART PHARMACY CACHE · INVC SUPPLY PLANNING</div>
                <h3 class="fw-bold mb-1"><i class="bi bi-capsule-pill"></i> ระบบบริหารยา: Supply Planning</h3>
                <div class="opacity-75">รายงานจาก MySQL cache ฐาน himtoinvc เท่านั้น ไม่เชื่อมต่อ SQL Server INVC โดยตรง</div>
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

    <div id="noDataBox" class="no-data mb-4 d-none">
        <h5 class="fw-bold mb-2"><i class="bi bi-database-x"></i> No synced data yet</h5>
        <div>ยังไม่พบข้อมูล Supply Planning สำหรับปีงบประมาณที่เลือก กรุณาตรวจสอบรอบ sync จาก Smart Pharmacy cache</div>
    </div>

    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="metric-label">ยอดคงคลัง ณ วันที่</div><div class="metric-value" id="stockQty">0</div><div class="metric-sub" id="stockDate">-</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card blue"><div class="metric-label">ยอด รพ.สต. ทั้งปีงบ</div><div class="metric-value" id="rpstYear">0</div><div class="metric-sub">ปริมาณรวมจาก cache</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card amber"><div class="metric-label">ประมาณการจัดซื้อทั้งปี</div><div class="metric-value" id="planQty">0</div><div class="metric-sub">จำนวนตามแผน</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card red"><div class="metric-label">มูลค่าแผนจัดซื้อทั้งปี</div><div class="metric-value" id="planValue">0</div><div class="metric-sub">บาท</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card purple"><div class="metric-label">ยอดซื้อแล้วทั้งปี</div><div class="metric-value" id="buyQtyCard">0</div><div class="metric-sub">มูลค่า <span id="buyValueCard">0</span> บาท</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card gray"><div class="metric-label">คงเหลือตามแผน</div><div class="metric-value" id="remainingQty">0</div><div class="metric-sub">มูลค่า <span id="remainingValue">0</span> บาท</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="metric-label">ความก้าวหน้าแผน</div><div class="metric-value" id="progressPctCard">0.00%</div><div class="metric-sub">buy_value / plan_value</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card blue"><div class="metric-label">ปีงบประมาณที่ใช้วิเคราะห์</div><div class="metric-value" id="resolvedFy">-</div><div class="metric-sub">อ้างอิงข้อมูล cache ล่าสุด</div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="metric-card"><div class="metric-label">บริษัทที่มีการซื้อ</div><div class="metric-value" id="vendorCount">0</div><div class="metric-sub">ผู้ขาย/บริษัทในปีงบ</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card blue"><div class="metric-label">จำนวนใบสั่งซื้อ</div><div class="metric-value" id="poCount">0</div><div class="metric-sub">PO จาก cache</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card amber"><div class="metric-label">จำนวนรายการซื้อ</div><div class="metric-value" id="vendorItemCount">0</div><div class="metric-sub">รวม total_item</div></div></div>
        <div class="col-xl-3 col-md-6"><div class="metric-card red"><div class="metric-label">มูลค่าซื้อจากบริษัท</div><div class="metric-value" id="vendorTotalCost">0</div><div class="metric-sub">รับแล้ว <span id="vendorReceivedCost">0</span> บาท</div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <section class="panel h-100">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-bar-chart-fill text-primary"></i> ยอด รพ.สต. รายไตรมาส</h5>
                        <div class="small-note">Q1-Q4 จาก app_invc_supply_planning_quarterly</div>
                    </div>
                    <span class="status-pill status-ok" id="dataStatus"><i class="bi bi-database-check"></i> Cache</span>
                </div>
                <div class="chart-canvas"><canvas id="quarterChart"></canvas></div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ไตรมาส</th>
                                <th class="text-end">ยอด รพ.สต.</th>
                                <th class="text-end">Synced at</th>
                            </tr>
                        </thead>
                        <tbody id="quarterRows">
                            <tr><td colspan="3" class="text-center text-muted">กำลังโหลด...</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>รวม</td>
                                <td class="text-end" id="quarterTotal">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="panel h-100">
                <h5 class="fw-bold mb-1"><i class="bi bi-clipboard2-check text-success"></i> ความก้าวหน้าแผนจัดซื้อ</h5>
                <div class="small-note mb-4">เทียบยอดซื้อแล้วกับมูลค่าแผนจัดซื้อทั้งปี</div>
                <div class="metric-label">Plan Progress</div>
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <div class="metric-value" id="progressPct">0.00%</div>
                    <div class="text-end">
                        <div class="small-note">ซื้อแล้ว</div>
                        <div class="fw-bold" id="buyValue">0</div>
                    </div>
                </div>
                <div class="progress-xl mb-3"><div id="progressBar" style="width:0%"></div></div>
                <div class="row g-2">
                    <div class="col-6"><div class="p-3 rounded-4 bg-light"><div class="small-note">จำนวนซื้อแล้ว</div><div class="fw-bold fs-5" id="buyQty">0</div></div></div>
                    <div class="col-6"><div class="p-3 rounded-4 bg-light"><div class="small-note">มูลค่าแผน</div><div class="fw-bold fs-5" id="planValueSmall">0</div></div></div>
                </div>
                <hr>
                <div class="small-note">อัปเดตล่าสุด: <span class="fw-bold text-dark" id="syncedAt">-</span></div>
                <div class="small-note">สถานะ Sync: <span class="fw-bold text-dark" id="syncStatus">-</span></div>
                <div class="small-note" id="syncMessage"></div>
                <hr>
                <div class="detail-row"><span class="detail-label">เริ่ม Sync</span><span class="detail-value" id="syncStarted">-</span></div>
                <div class="detail-row"><span class="detail-label">เสร็จสิ้น Sync</span><span class="detail-value" id="syncFinished">-</span></div>
                <div class="sync-grid mt-3">
                    <div class="sync-box"><div class="small-note">Header</div><div class="fw-bold fs-5" id="rowsHeader">0</div></div>
                    <div class="sync-box"><div class="small-note">Item</div><div class="fw-bold fs-5" id="rowsItem">0</div></div>
                    <div class="sync-box"><div class="small-note">Summary</div><div class="fw-bold fs-5" id="rowsSummary">0</div></div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <section class="panel h-100">
                <h5 class="fw-bold mb-1"><i class="bi bi-buildings text-primary"></i> ซื้อยาจากบริษัทสูงสุด</h5>
                <div class="small-note mb-3">Top 10 บริษัทตามมูลค่าใบสั่งซื้อ จาก app_invc_purchase_headers</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>บริษัท</th>
                                <th class="text-end">PO</th>
                                <th class="text-end">มูลค่า</th>
                            </tr>
                        </thead>
                        <tbody id="vendorRows">
                            <tr><td colspan="3" class="text-center text-muted">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="panel h-100">
                <h5 class="fw-bold mb-1"><i class="bi bi-capsule text-success"></i> รายการซื้อยาล่าสุด</h5>
                <div class="small-note mb-3">15 รายการล่าสุด จาก app_invc_purchase_items</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>PO / วันที่</th>
                                <th>ยา</th>
                                <th>บริษัท</th>
                                <th class="text-end">จำนวน</th>
                                <th class="text-end">มูลค่า</th>
                            </tr>
                        </thead>
                        <tbody id="recentItemRows">
                            <tr><td colspan="5" class="text-center text-muted">กำลังโหลด...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="panel">
        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-info"></i> แหล่งข้อมูลรายงาน</h5>
        <div class="row g-3">
            <div class="col-lg-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small-note">Database</div><div class="fw-bold">192.168.111.240 / himtoinvc</div></div></div>
            <div class="col-lg-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small-note">Summary Table</div><div class="fw-bold">app_invc_supply_planning_summary</div></div></div>
            <div class="col-lg-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small-note">Quarterly Table</div><div class="fw-bold">app_invc_supply_planning_quarterly</div></div></div>
        </div>
    </section>
</main>

<?php include_once __DIR__ . '/../../layout/footer.php'; ?>
<script>
let quarterChart=null;
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('btnReload').addEventListener('click',loadSupplyPlanning);document.getElementById('fiscalYear').addEventListener('change',loadSupplyPlanning);loadSupplyPlanning()});
async function loadSupplyPlanning(){try{const fy=document.getElementById('fiscalYear').value;const r=await fetch(`../../api/get_supply_planning.php?fiscal_year=${encodeURIComponent(fy)}`,{credentials:'same-origin'});const d=await r.json();if(!r.ok||d.status!=='success')throw new Error(d.message||'โหลดข้อมูลไม่สำเร็จ');renderSupply(d)}catch(e){Swal.fire('โหลดข้อมูลระบบบริหารยาไม่สำเร็จ',e.message,'error')}}
function renderSupply(d){const found=!!d.data_found;document.getElementById('noDataBox').classList.toggle('d-none',found);document.getElementById('summaryCards').classList.toggle('opacity-50',!found);const s=d.summary||{};set('stockQty',num(s.total_stock_qty));set('stockDate',s.stock_as_of_date?`ณ วันที่ ${s.stock_as_of_date}`:'-');set('rpstYear',num(s.rpst_total_qty));set('planQty',num(s.plan_qty));set('planValue',money(s.plan_value));set('buyValue',money(s.buy_value));set('buyQty',num(s.buy_qty));set('buyQtyCard',num(s.buy_qty));set('buyValueCard',money(s.buy_value));set('remainingQty',num(s.remaining_qty));set('remainingValue',money(s.remaining_value));set('progressPctCard',`${num(s.plan_progress_pct,2)}%`);set('resolvedFy',d.fiscal_year||'-');set('planValueSmall',money(s.plan_value));set('progressPct',`${num(s.plan_progress_pct,2)}%`);set('syncedAt',s.synced_at||'-');const pct=Math.max(0,Math.min(Number(s.plan_progress_pct||0),100));document.getElementById('progressBar').style.width=`${pct}%`;const st=d.sync_status||{};set('syncStatus',st.status||'-');set('syncMessage',st.message||'');set('syncStarted',st.started_at||'-');set('syncFinished',st.finished_at||'-');set('rowsHeader',num(st.rows_header));set('rowsItem',num(st.rows_item));set('rowsSummary',num(st.rows_summary));const vendors=d.vendor_purchases||{};set('vendorCount',num(vendors.vendor_count));set('poCount',num(vendors.po_count));set('vendorItemCount',num(vendors.item_count));set('vendorTotalCost',money(vendors.total_cost));set('vendorReceivedCost',money(vendors.total_cost_received));renderVendorRows(vendors.top_vendors||[]);renderRecentItems(vendors.recent_purchase_items||[]);document.getElementById('dataStatus').className=`status-pill ${found?'status-ok':'status-warn'}`;document.getElementById('dataStatus').innerHTML=found?'<i class="bi bi-database-check"></i> Cache พร้อมใช้':'<i class="bi bi-database-x"></i> ยังไม่มีข้อมูล';const quarters=d.quarters||{labels:['Q1','Q2','Q3','Q4'],rpst_total_qty:[0,0,0,0],rows:[],total:0};renderQuarterChart(quarters);renderQuarterRows(quarters)}
function renderQuarterRows(q){const rows=q.rows||[];document.getElementById('quarterRows').innerHTML=rows.length?rows.map(x=>`<tr><td class="fw-bold">${esc(x.quarter_label)}</td><td class="text-end">${num(x.rpst_total_qty)}</td><td class="text-end text-muted">${esc(x.synced_at||'-')}</td></tr>`).join(''):'<tr><td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูลรายไตรมาส</td></tr>';set('quarterTotal',num(q.total))}
function renderVendorRows(rows){document.getElementById('vendorRows').innerHTML=rows.length?rows.map(x=>`<tr><td><div class="fw-bold">${esc(x.company_name||'-')}</div><div class="small-note">${esc(x.vendor_code||'')}</div></td><td class="text-end">${num(x.po_count)}</td><td class="text-end fw-bold">${money(x.total_cost)}</td></tr>`).join(''):'<tr><td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูลซื้อจากบริษัท</td></tr>'}
function renderRecentItems(rows){document.getElementById('recentItemRows').innerHTML=rows.length?rows.map(x=>`<tr><td><div class="fw-bold">${esc(x.po_no||'-')}</div><div class="small-note">${esc(x.po_date||'-')}</div></td><td><div class="fw-bold">${esc(x.drug_name||'-')}</div><div class="small-note">${esc(x.working_code||'')}</div></td><td><div>${esc(x.company_name||'-')}</div><div class="small-note">${esc(x.vendor_code||'')}</div></td><td class="text-end">${num(x.qty_order_pack,2)} ${esc(x.po_unit||'')}</td><td class="text-end fw-bold">${money(x.buy_value)}</td></tr>`).join(''):'<tr><td colspan="5" class="text-center text-muted">ยังไม่มีรายการซื้อยา</td></tr>'}
function renderQuarterChart(q){if(quarterChart)quarterChart.destroy();quarterChart=new Chart(document.getElementById('quarterChart'),{type:'bar',data:{labels:q.labels||[],datasets:[{label:'ยอด รพ.สต.',data:q.rpst_total_qty||[],backgroundColor:'#0f766e',borderRadius:8}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>num(v)}}}}})}
function set(id,v){document.getElementById(id).textContent=v}
function num(v,d=0){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:d,maximumFractionDigits:d})}
function money(v){return Number(v||0).toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2})}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
</script>

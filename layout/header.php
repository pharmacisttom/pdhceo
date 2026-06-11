<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PDH CEO Executive Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
body {
    font-family: 'Sarabun', sans-serif;
    background: #f2f5fa;
}
/* การจัดการ Layout Sidebar */
.sidebar {
    width: 260px;
    min-height: 100vh;
    background: linear-gradient(180deg, #0F172A, #1E3A8A);
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px;
    z-index: 1000;
}
.logo {
    color: #fff;
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 35px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 20px;
}
.menu {
    display: block;
    padding: 12px 15px;
    margin-bottom: 8px;
    color: #dbeafe;
    text-decoration: none;
    border-radius: 10px;
    transition: .3s;
    font-size: 15px;
}
.menu i { margin-right: 10px; font-size: 18px; }
.menu:hover {
    background: rgba(255,255,255,.1);
    color: white;
}
.active-menu {
    background: #2563eb;
    color: #fff;
    box-shadow: 0 4px 15px rgba(37,99,235,0.4);
}
.menu-header {
    color: #94a3b8;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 20px 0 10px 10px;
    letter-spacing: 1px;
}

/* ส่วนแสดงเนื้อหา Content Area */
.content {
    margin-left: 260px;
    padding: 30px;
}
.topbar {
    background: white;
    padding: 20px 25px;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,.03);
}

/* การตกแต่งการ์ด KPI สไตล์ Modern Gradient */
.cardx {
    border: none;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,.04);
    color: white;
    overflow: hidden;
    position: relative;
    transition: transform 0.2s;
}
.cardx:hover {
    transform: translateY(-5px);
}
.bg-opd { background: linear-gradient(135deg, #2563eb, #3b82f6); }
.bg-ipd { background: linear-gradient(135deg, #059669, #10b981); }
.bg-er { background: linear-gradient(135deg, #dc2626, #ef4444); }
.bg-occ { background: linear-gradient(135deg, #d97706, #f59e0b); }
.bg-rw { background: linear-gradient(135deg, #7c3aed, #8b5cf6); }
.bg-refer { background: linear-gradient(135deg, #475569, #64748b); }

.kpi {
    font-size: 32px;
    font-weight: 700;
    margin-top: 5px;
}
.smalltext {
    opacity: .9;
    font-size: 14px;
}
.kpi-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 40px;
    opacity: 0.25;
}

/* บล็อกกราฟและตาราง */
.chartbox {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,.03);
    height: 100%;
}
.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}
.table thead {
    background: #f8fafc;
    color: #475569;
    font-size: 14px;
}

.pdh-global-loader {
    position: fixed;
    inset: 0;
    z-index: 3000;
    pointer-events: none;
    opacity: 0;
    transition: opacity .18s ease;
}
.pdh-global-loader.is-visible {
    opacity: 1;
}
.pdh-loader-bar {
    position: fixed;
    top: 0;
    left: 0;
    height: 4px;
    width: 100%;
    background: rgba(37, 99, 235, .12);
    overflow: hidden;
}
.pdh-loader-bar::before {
    content: "";
    position: absolute;
    top: 0;
    left: -35%;
    width: 35%;
    height: 100%;
    background: linear-gradient(90deg, #22c55e, #2563eb, #06b6d4);
    animation: pdhLoaderSlide 1.05s ease-in-out infinite;
}
.pdh-loader-panel {
    position: fixed;
    right: 22px;
    top: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    color: #0f172a;
    background: rgba(255, 255, 255, .96);
    border: 1px solid rgba(148, 163, 184, .35);
    border-radius: 12px;
    box-shadow: 0 14px 35px rgba(15, 23, 42, .12);
    font-weight: 700;
    font-size: 14px;
}
.pdh-loader-spinner {
    width: 18px;
    height: 18px;
    border: 3px solid #dbeafe;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: pdhLoaderSpin .8s linear infinite;
}
@keyframes pdhLoaderSlide {
    0% { left: -35%; }
    100% { left: 100%; }
}
@keyframes pdhLoaderSpin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>
<div class="pdh-global-loader is-visible" id="pdhGlobalLoader" aria-live="polite" aria-label="กำลังโหลดข้อมูล">
    <div class="pdh-loader-bar"></div>
    <div class="pdh-loader-panel">
        <span class="pdh-loader-spinner"></span>
        <span id="pdhLoaderText">กำลังโหลดข้อมูล...</span>
    </div>
</div>

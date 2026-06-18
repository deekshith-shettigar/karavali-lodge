<?php
require_once __DIR__ . '/../user/includes/config.php';
if (!isAdminSession()) {
    $_SESSION['redirect_after_login'] = ADMIN_URL . '/index.php';
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminId   = $_SESSION['admin_id']   ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karavali Lodge - Lodge Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <link href="css/style.css" rel="stylesheet">
    <style>
        .sync-bar { background:#f8f9fa;border-bottom:1px solid #e0e0e0;padding:4px 20px;display:flex;align-items:center;justify-content:space-between;font-size:0.78rem;gap:12px; }
        .sync-bar .sync-left { display:flex;align-items:center;gap:10px; }
        #syncStatus { font-weight:500;color:#6c757d; }
        .req-badge { background:#dc3545;color:#fff;font-size:0.65rem;padding:1px 6px;border-radius:50px;margin-left:6px;vertical-align:middle;display:none;font-weight:700; }
        .req-badge.show { display:inline; }
        .badge-available   { background:#28a745!important;color:#fff!important; }
        .badge-occupied    { background:#dc3545!important;color:#fff!important; }
        .badge-reserved    { background:#ffc107!important;color:#333!important; }
        .badge-cleaning    { background:#17a2b8!important;color:#fff!important; }
        .badge-maintenance { background:#6c757d!important;color:#fff!important; }
        .badge-dirty       { background:#e74c3c!important;color:#fff!important; }
        .badge-clean       { background:#27ae60!important;color:#fff!important; }
        .badge-checkedin   { background:#2ecc71!important;color:#fff!important; }
        .badge-checkedout  { background:#95a5a6!important;color:#fff!important; }
        .badge-pending     { background:#f39c12!important;color:#333!important; }
        .badge-confirmed   { background:#3498db!important;color:#fff!important; }
        .badge-cancelled   { background:#e74c3c!important;color:#fff!important; }
        .badge-completed   { background:#27ae60!important;color:#fff!important; }
        .badge-preparing   { background:#f39c12!important;color:#333!important; }
        .badge-delivered   { background:#27ae60!important;color:#fff!important; }
        .badge-paid        { background:#27ae60!important;color:#fff!important; }
        .badge-unpaid      { background:#e74c3c!important;color:#fff!important; }
        .badge-new         { background:#dc3545!important;color:#fff!important; }
        .badge-read        { background:#6c757d!important;color:#fff!important; }
        .req-card { background:#fff;border:1px solid #E8D9C0;border-left:4px solid #C4943A;border-radius:10px;padding:16px 20px;margin-bottom:12px;transition:box-shadow 0.2s; }
        .req-card:hover { box-shadow:0 4px 16px rgba(59,26,10,0.1); }
        .req-card .req-no { font-size:0.72rem;letter-spacing:1.5px;text-transform:uppercase;color:#C4943A;font-weight:700;margin-bottom:4px; }
        .req-card .req-guest { font-weight:700;color:#3B1A0A;font-size:1rem; }
        .req-card .req-meta { font-size:0.82rem;color:#8B7355;margin-top:4px; }
        .req-card .req-actions { margin-top:12px;display:flex;gap:8px;flex-wrap:wrap; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.4);} }
        .pulse-dot { width:8px;height:8px;background:#dc3545;border-radius:50%;display:inline-block;animation:pulse-dot 1.2s infinite; }
    </style>
</head>
<body>
<div class="d-flex" id="wrapper">

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="hotel-logo">
                <img src="images/Lodge_Logoo.png" alt="Lodge Logo"
                     onerror="this.parentElement.innerHTML='<i class=\'bi bi-gem\' style=\'font-size:1.8rem;color:#C4943A\'></i>'">
            </div>
            <h5 class="hotel-name">Karavali Lodge</h5>
            <small class="text-muted">Lodge Management System</small>
        </div>

        <nav class="sidebar-nav">

            <!-- 1. Dashboard -->
            <a href="#" class="nav-link active" data-module="dashboard" data-title="Dashboard">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>

            <!-- 2. Online Requests -->
            <a href="#" class="nav-link" id="onlineRequestsLink" data-module="online_requests">
                <i class="bi bi-globe"></i>
                <span>Online Requests</span>
                <span class="req-badge" id="reqBadge">0</span>
            </a>

            <!-- 3. Room Management -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="rooms" data-title="Room Management">
                    <i class="bi bi-door-open"></i><span>Room Management</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="rooms"><i class="bi bi-list-ul"></i><span>Room List</span></a>
                    <a href="#" class="nav-sub-link" data-module="rooms" data-action="addRoom"><i class="bi bi-plus-circle"></i><span>Add Room</span></a>
                </div>
            </div>

            <!-- 4. Booking / Reservation -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="booking" data-title="Booking">
                    <i class="bi bi-calendar-check"></i><span>Booking / Reservation</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="booking"><i class="bi bi-card-list"></i><span>All Bookings</span></a>
                    <a href="#" class="nav-sub-link" data-module="booking" data-action="newBooking"><i class="bi bi-plus-circle"></i><span>New Booking</span></a>
                </div>
            </div>

            <!-- 5. Guest Management -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="guests" data-title="Guests">
                    <i class="bi bi-people"></i><span>Guest Management</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="guests"><i class="bi bi-person-lines-fill"></i><span>Guest Directory</span></a>
                    <a href="#" class="nav-sub-link" data-module="guests" data-action="addGuest"><i class="bi bi-person-plus"></i><span>Add Guest</span></a>
                </div>
            </div>

            <!-- 6. ID Verification -->
            <a href="#" class="nav-link" data-module="idverify" data-title="ID Verification">
                <i class="bi bi-person-badge"></i><span>ID Verification</span>
            </a>

            <!-- 7. Check-In / Check-Out -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="checkin" data-title="Check-In/Out">
                    <i class="bi bi-box-arrow-in-right"></i><span>Check-In / Check-Out</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="checkin"><i class="bi bi-box-arrow-in-right"></i><span>Check-In</span></a>
                    <a href="#" class="nav-sub-link" data-module="checkin" data-action="checkouts"><i class="bi bi-box-arrow-right"></i><span>Checked-In Guests</span></a>
                </div>
            </div>

            <!-- 8. Billing -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="billing" data-title="Billing">
                    <i class="bi bi-receipt"></i><span>Billing</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="billing"><i class="bi bi-file-earmark-text"></i><span>Bills &amp; Invoices</span></a>
                    <a href="#" class="nav-sub-link" data-module="billing" data-action="payments"><i class="bi bi-cash-stack"></i><span>Payments</span></a>
                </div>
            </div>

            <!-- 9. Availability Calendar -->
            <a href="#" class="nav-link" data-module="calendar" data-title="Calendar">
                <i class="bi bi-calendar3"></i><span>Availability Calendar</span>
            </a>

            <!-- 10. Room Service -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="roomservice" data-title="Room Service">
                    <i class="bi bi-cup-straw"></i><span>Room Service</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="roomservice"><i class="bi bi-cart-plus"></i><span>New Order</span></a>
                    <a href="#" class="nav-sub-link" data-module="roomservice" data-action="orders"><i class="bi bi-receipt-cutoff"></i><span>Orders List</span></a>
                </div>
            </div>

            <!-- 11. Amenity / Services -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="amenities" data-title="Amenities">
                    <i class="bi bi-stars"></i><span>Amenity / Services</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="amenities"><i class="bi bi-tags"></i><span>Service Catalog</span></a>
                    <a href="#" class="nav-sub-link" data-module="amenities" data-action="assign"><i class="bi bi-person-check"></i><span>Assign Service</span></a>
                </div>
            </div>

            <!-- 12. Housekeeping -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="housekeeping" data-title="Housekeeping">
                    <i class="bi bi-brush"></i><span>Housekeeping</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="housekeeping"><i class="bi bi-grid-3x3-gap"></i><span>Status Board</span></a>
                    <a href="#" class="nav-sub-link" data-module="housekeeping" data-action="tasks"><i class="bi bi-clipboard-check"></i><span>Active Tasks</span></a>
                </div>
            </div>

            <!-- 13. Contact Messages -->
            <a href="#" class="nav-link" id="messagesLink">
                <i class="bi bi-chat-dots"></i>
                <span>Contact Messages</span>
                <span class="req-badge" id="msgBadge">0</span>
            </a>

            <!-- 14. Night Audit -->
            <a href="#" class="nav-link" data-module="nightaudit" data-title="Night Audit">
                <i class="bi bi-moon-stars"></i><span>Night Audit</span>
            </a>

            <!-- 15. Reports -->
            <div class="nav-item-dropdown">
                <a href="#" class="nav-link dropdown-toggle-link" data-module="reports" data-title="Reports">
                    <i class="bi bi-graph-up"></i><span>Reports</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="#" class="nav-sub-link" data-module="reports" data-action="occupancy"><i class="bi bi-pie-chart"></i><span>Occupancy Report</span></a>
                    <a href="#" class="nav-sub-link" data-module="reports" data-action="revenue"><i class="bi bi-currency-rupee"></i><span>Revenue Report</span></a>
                    <a href="#" class="nav-sub-link" data-module="reports" data-action="guest"><i class="bi bi-people"></i><span>Guest Report</span></a>
                    <a href="#" class="nav-sub-link" data-module="reports" data-action="services"><i class="bi bi-gear"></i><span>Services Report</span></a>
                </div>
            </div>

        </nav>
    </div><!-- /sidebar -->

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">

        <div class="sync-bar">
            <div class="sync-left">
                <span class="pulse-dot" id="syncDot" style="display:none"></span>
                <span id="syncStatus">Connecting to database...</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="DB.pullFromMySQL().then(()=>{ if(window.LodgeApp) LodgeApp.navigate(LodgeApp.currentModule); })">
                    <i class="bi bi-arrow-clockwise"></i> Sync Now
                </button>
                <a href="http://localhost/karavali_lodge/user/" target="_blank"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-globe me-1"></i>User Website
                </a>
            </div>
        </div>

        <nav class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-sm sidebar-toggle-btn me-2" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="mb-0 ms-2 page-title" id="pageTitle">Dashboard</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small" id="currentDateTime"></span>
                <div style="position:relative;display:inline-flex;">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="document.getElementById('messagesLink').click();return false;"
                            title="Contact Messages" id="msgBellBtn"
                            style="padding:6px 11px;font-size:1rem;">
                        <i class="bi bi-envelope"></i>
                    </button>
                    <span id="msgTopbarBadge"
                          style="display:none;position:absolute;top:2px;right:2px;background:#dc3545;color:#fff;border-radius:50%;width:16px;height:16px;font-size:0.6rem;font-weight:700;line-height:16px;text-align:center;pointer-events:none;z-index:1;">0</span>
                </div>
                <div style="position:relative;display:inline-flex;">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="document.getElementById('onlineRequestsLink').click();return false;"
                            title="Online Booking Requests" id="reqBellBtn"
                            style="padding:6px 11px;font-size:1rem;">
                        <i class="bi bi-calendar-check"></i>
                    </button>
                    <span id="reqTopbarBadge"
                          style="display:none;position:absolute;top:2px;right:2px;background:#dc3545;color:#fff;border-radius:50%;width:16px;height:16px;font-size:0.6rem;font-weight:700;line-height:16px;text-align:center;pointer-events:none;z-index:1;">0</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <span id="adminNameDisplay"><?= htmlspecialchars($adminName) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:220px;">
                        <li>
                            <div class="px-3 py-2 border-bottom">
                                <div style="font-size:0.7rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Logged in as</div>
                                <div style="font-weight:700;color:#3B1A0A;font-size:0.92rem;" id="adminNameFull"><?= htmlspecialchars($adminName) ?></div>
                                <div style="font-size:0.78rem;color:#C4943A;" id="adminMobileDisplay"></div>
                            </div>
                        </li>
                        <li><a class="dropdown-item" href="#" onclick="LodgeApp.navigate('nightaudit');return false;"><i class="bi bi-moon-stars me-2"></i>Night Audit</a></li>
                        <li><a class="dropdown-item" href="#" onclick="LodgeApp.navigate('reports');return false;"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
                        <li><a class="dropdown-item" href="<?= SITE_URL ?>/index.php" target="_blank"><i class="bi bi-globe me-2"></i>View Website</a></li>
                        <li><a class="dropdown-item" href="setup_admin.php?key=RHddF1eLPVa1mIjrWV7fgludyKTk65Lx351yqVEMB64hXqs1"><i class="bi bi-people me-2"></i>Manage Admins</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/pages/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="content-area" id="contentArea"></div>
    </div><!-- /main-content -->
</div><!-- /wrapper -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/db.js"></script>
<script src="js/utils.js"></script>
<script src="js/rooms.js"></script>
<script src="js/booking.js"></script>
<script src="js/guests.js"></script>
<script src="js/checkin.js"></script>
<script src="js/housekeeping.js"></script>
<script src="js/roomservice.js"></script>
<script src="js/billing.js"></script>
<script src="js/amenities.js"></script>
<script src="js/calendar.js"></script>
<script src="js/nightaudit.js"></script>
<script src="js/reports.js"></script>
<script src="js/idverify.js"></script>
<script src="js/dashboard.js"></script>
<script src="js/app.js"></script>

<script>
/* ==========================================
   Online Booking Requests Module
   ========================================== */
const OnlineRequestsModule = {
    render() {
        const requests = DB.getAll(DB.ONLINE_REQUESTS);
        const pending  = requests.filter(b => b.status === 'Pending');
        return `
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3"><div class="stat-card"><div class="d-flex justify-content-between"><div><div class="stat-value">${requests.length}</div><div class="stat-label">Total Requests</div></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-globe"></i></div></div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#f39c12"><div class="d-flex justify-content-between"><div><div class="stat-value">${pending.length}</div><div class="stat-label">Pending</div></div><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock"></i></div></div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#28a745"><div class="d-flex justify-content-between"><div><div class="stat-value">${requests.filter(b=>b.status==='Confirmed').length}</div><div class="stat-label">Confirmed</div></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div></div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#dc3545"><div class="d-flex justify-content-between"><div><div class="stat-value">${requests.filter(b=>b.status==='Rejected'||b.status==='Cancelled').length}</div><div class="stat-label">Rejected</div></div><div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div></div></div></div>
            </div>
            <div class="module-card">
                <div class="card-header">
                    <span><i class="bi bi-globe me-2"></i>Online Booking Requests from Website</span>
                    <button class="btn btn-sm btn-light" onclick="DB.pullFromMySQL().then(()=>{ document.getElementById('contentArea').innerHTML = OnlineRequestsModule.render(); })"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </div>
                <div class="card-body">
                    ${requests.length === 0
                        ? `<div class="text-center py-5"><i class="bi bi-inbox" style="font-size:3rem;color:#dee2e6;display:block;margin-bottom:12px"></i><p class="text-muted mb-0">No online booking requests yet.</p><small class="text-muted">Requests from the user website will appear here.</small></div>`
                        : requests.sort((a,b)=>new Date(b.createdAt)-new Date(a.createdAt)).map(req=>`
                            <div class="req-card">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div style="flex:1;min-width:0">
                                        <div class="req-no">${Utils.escapeHtml(req.bookingNo||req.id)}</div>
                                        <div class="req-guest">${Utils.escapeHtml(req.guestName)}</div>
                                        <div class="req-meta"><i class="bi bi-telephone me-1"></i>${Utils.escapeHtml(req.mobile)}${req.email?` &nbsp;·&nbsp;<i class="bi bi-envelope me-1"></i>${Utils.escapeHtml(req.email)}`:''}</div>
                                        <div class="req-meta"><i class="bi bi-door-open me-1"></i>${Utils.escapeHtml(req.roomType||'-')}${req.roomNumber?` · Room ${Utils.escapeHtml(req.roomNumber)}`:''} &nbsp;·&nbsp; <i class="bi bi-calendar-range me-1"></i>${Utils.formatDate(req.checkIn)} → ${Utils.formatDate(req.checkOut)} &nbsp;·&nbsp; <i class="bi bi-people me-1"></i>${req.numGuests||1} Guest(s)</div>
                                        ${req.specialRequests?`<div class="req-meta" style="font-style:italic"><i class="bi bi-chat-text me-1"></i>${Utils.escapeHtml(req.specialRequests)}</div>`:''}
                                        ${req.totalAmount?`<div class="req-meta"><strong style="color:#C4943A">Total: ${Utils.formatCurrency(req.totalAmount)}</strong></div>`:''}
                                        <div class="req-meta" style="color:#bbb;font-size:0.75rem">Received: ${Utils.formatDate(req.createdAt)}</div>
                                    </div>
                                    <div>${Utils.statusBadge(req.status)}</div>
                                </div>
                                ${req.status==='Pending'?`<div class="req-actions"><button class="btn btn-sm btn-success" onclick="OnlineRequestsModule.confirm('${req.id}')"><i class="bi bi-check-lg me-1"></i>Confirm Booking</button><button class="btn btn-sm btn-outline-danger" onclick="OnlineRequestsModule.reject('${req.id}')"><i class="bi bi-x-lg me-1"></i>Reject</button></div>`:''}
                            </div>`).join('')}
                </div>
            </div>`;
    },
    async confirm(reqId) {
        if (!confirm('Confirm this booking? The room will be marked as Reserved.')) return;
        Utils.showToast('Confirming...','info');
        const result = await DB.confirmOnlineRequest(reqId);
        if (result && result.success) {
            Utils.showToast('Booking confirmed! Ref: '+result.booking_no,'success');
            document.getElementById('contentArea').innerHTML = OnlineRequestsModule.render();
        } else {
            Utils.showToast('Could not confirm — check database connection.','danger');
        }
    },
    async reject(reqId) {
        if (!confirm('Reject this booking request?')) return;
        const numericId = String(reqId).replace('req_','');
        try {
            const res  = await fetch('api.php?action=reject_request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({request_id:numericId})});
            const json = await res.json();
            if (!json.success){Utils.showToast('Could not reject — database error','danger');return;}
        } catch(e){Utils.showToast('Could not connect to server','danger');return;}
        const all = DB._getStore('online_requests');
        const idx = all.findIndex(b=>b.id===reqId);
        if (idx>=0){all[idx].status='Rejected';localStorage.setItem('kl_online_requests',JSON.stringify(all));}
        Utils.showToast('Booking request rejected','info');
        document.getElementById('contentArea').innerHTML = OnlineRequestsModule.render();
        DB._updateRequestBadge();
    }
};

/* ==========================================
   Contact Messages Module
   ========================================== */
const MessagesModule = {
    async load() {
        try {
            const res  = await fetch(API_URL+'?action=get_messages');
            const json = await res.json();
            if (!json.success) throw new Error('Failed');
            document.getElementById('contentArea').innerHTML = MessagesModule.render(json.messages);
        } catch(e) {
            document.getElementById('contentArea').innerHTML = '<div class="alert alert-danger m-4">Could not load messages.</div>';
        }
    },
    render(messages) {
        const total=messages.length, unread=messages.filter(m=>m.status==='New').length,
              read=messages.filter(m=>m.status==='Read').length, replied=messages.filter(m=>m.adminReply).length;
        return `
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3"><div class="stat-card"><div class="d-flex justify-content-between"><div><div class="stat-value">${total}</div><div class="stat-label">Total Messages</div></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-chat-dots"></i></div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#dc3545"><div class="d-flex justify-content-between"><div><div class="stat-value">${unread}</div><div class="stat-label">Unread</div></div><div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-envelope-fill"></i></div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#28a745"><div class="d-flex justify-content-between"><div><div class="stat-value">${read}</div><div class="stat-label">Read</div></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-envelope-open"></i></div></div></div></div>
            <div class="col-6 col-md-3"><div class="stat-card" style="border-left-color:#C4943A"><div class="d-flex justify-content-between"><div><div class="stat-value">${replied}</div><div class="stat-label">Replied</div></div><div class="stat-icon" style="background:rgba(196,148,58,0.1);color:#C4943A"><i class="bi bi-reply-fill"></i></div></div></div></div>
        </div>
        <div class="module-card">
            <div class="card-header"><span><i class="bi bi-chat-dots me-2"></i>Messages from Website</span><button class="btn btn-sm btn-light" onclick="MessagesModule.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button></div>
            <div class="card-body p-0">
                ${total===0?`<div class="text-center py-5"><i class="bi bi-inbox" style="font-size:3rem;color:#dee2e6;display:block;margin-bottom:12px"></i><p class="text-muted mb-0">No messages yet.</p></div>`:
                messages.map(m=>`
                    <div class="p-3 border-bottom" style="background:${m.status==='New'?'#fffbf5':'#fff'}">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div style="flex:1;min-width:0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong style="color:#3B1A0A">${Utils.escapeHtml(m.name)}</strong>
                                    ${m.status==='New'?'<span class="badge" style="background:#dc3545;font-size:0.65rem;">NEW</span>':m.adminReply?'<span class="badge" style="background:#C4943A;font-size:0.65rem;">REPLIED</span>':'<span class="badge" style="background:#6c757d;font-size:0.65rem;">READ</span>'}
                                </div>
                                <div style="font-size:0.82rem;color:#8B7355;margin-bottom:6px;">
                                    ${m.mobile?`<i class="bi bi-telephone me-1"></i>${Utils.escapeHtml(m.mobile)}&nbsp;&nbsp;`:''}
                                    ${m.email?`<i class="bi bi-envelope me-1"></i>${Utils.escapeHtml(m.email)}&nbsp;&nbsp;`:''}
                                    <i class="bi bi-clock me-1"></i>${Utils.formatDate(m.createdAt)}
                                </div>
                                ${m.subject?`<div style="font-size:0.82rem;font-weight:600;color:#C4943A;margin-bottom:6px;"><i class="bi bi-tag me-1"></i>${Utils.escapeHtml(m.subject)}</div>`:''}
                            </div>
                            <div class="d-flex flex-column gap-2" style="min-width:120px">
                                ${m.status==='New'?`<button class="btn btn-sm btn-success" onclick="MessagesModule.markRead(${m.id})"><i class="bi bi-check2 me-1"></i>Mark Read</button>`:`<button class="btn btn-sm btn-outline-secondary" onclick="MessagesModule.markUnread(${m.id})"><i class="bi bi-envelope me-1"></i>Unread</button>`}
                                <button class="btn btn-sm btn-outline-danger" onclick="MessagesModule.deleteMsg(${m.id})"><i class="bi bi-trash me-1"></i>Delete</button>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;margin-bottom:12px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:#f0e8d8;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;font-weight:700;color:#8B7355;">${Utils.escapeHtml(m.name.charAt(0).toUpperCase())}</div>
                            <div style="flex:1">
                                <div style="font-size:0.78rem;color:#8B7355;margin-bottom:4px;font-weight:600;">${Utils.escapeHtml(m.name)} <span style="font-weight:400">${Utils.formatDate(m.createdAt)}</span></div>
                                <div style="font-size:0.9rem;color:#3B1A0A;line-height:1.6;background:#f8f4ef;border-radius:0 10px 10px 10px;padding:10px 14px;">${Utils.escapeHtml(m.message)}</div>
                            </div>
                        </div>
                        ${m.adminReply?`<div style="display:flex;gap:10px;margin-bottom:12px;flex-direction:row-reverse;"><div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#C4943A,#D4AD5E);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;color:#2A1007;font-weight:700;">A</div><div style="flex:1;text-align:right;"><div style="font-size:0.78rem;color:#8B7355;margin-bottom:4px;font-weight:600;">Admin <span style="font-weight:400">${m.repliedAt?Utils.formatDate(m.repliedAt):''}</span></div><div style="font-size:0.9rem;color:#fff;line-height:1.6;background:linear-gradient(135deg,#3B1A0A,#5C2D0E);border-radius:10px 0 10px 10px;padding:10px 14px;display:inline-block;max-width:90%;text-align:left;">${Utils.escapeHtml(m.adminReply)}</div></div></div>`:''}
                        <div style="border-top:1px solid #f0e8d8;padding-top:12px;margin-top:4px;">
                            <div style="font-size:0.78rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#C4943A;margin-bottom:8px;"><i class="bi bi-reply-fill me-1"></i>${m.adminReply?'Edit Reply':'Reply to Guest'}</div>
                            <div style="display:flex;gap:10px;align-items:flex-start;">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#C4943A,#D4AD5E);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;color:#2A1007;font-weight:700;margin-top:2px;">A</div>
                                <div style="flex:1;">
                                    <textarea id="replyBox_${m.id}" style="width:100%;border:1.5px solid #E8D9C0;border-radius:10px;padding:10px 14px;font-size:0.88rem;font-family:'Jost',sans-serif;resize:vertical;min-height:80px;background:#fdfaf5;color:#3B1A0A;" onfocus="this.style.borderColor='#C4943A'" onblur="this.style.borderColor='#E8D9C0'" placeholder="Type your reply to ${Utils.escapeHtml(m.name)}...">${m.adminReply?Utils.escapeHtml(m.adminReply):''}</textarea>
                                    <div style="display:flex;gap:8px;margin-top:8px;justify-content:flex-end;">
                                        <button onclick="MessagesModule.sendReply(${m.id},'${Utils.escapeHtml(m.name)}')" id="replyBtn_${m.id}" style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;border:none;border-radius:8px;padding:8px 20px;font-weight:700;font-size:0.82rem;cursor:pointer;display:flex;align-items:center;gap:6px;">
                                            <i class="bi bi-send-fill"></i> ${m.adminReply?'Update Reply':'Send Reply'}
                                        </button>
                                        ${m.adminReply?`<button onclick="MessagesModule.clearReply(${m.id})" style="background:#f8f4ef;color:#8B7355;border:1.5px solid #E8D9C0;border-radius:8px;padding:8px 14px;font-size:0.82rem;cursor:pointer;">Clear</button>`:''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`).join('')}
            </div>
        </div>`;
    },
    async markRead(id)   { await fetch(API_URL+'?action=update_message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,status:'Read'})});   await MessagesModule.load(); },
    async markUnread(id) { await fetch(API_URL+'?action=update_message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,status:'New'})});    await MessagesModule.load(); },
    async deleteMsg(id)  { if(!confirm('Delete this message?')) return; await fetch(API_URL+'?action=delete_message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); await MessagesModule.load(); },
    async sendReply(id, guestName) {
        const box=document.getElementById('replyBox_'+id), btn=document.getElementById('replyBtn_'+id);
        const reply=box?box.value.trim():'';
        if(!reply){alert('Please type a reply before sending.');box?.focus();return;}
        btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
        const res=await fetch(API_URL+'?action=reply_message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,reply,admin:'Admin'})});
        const data=await res.json();
        if(data.success){btn.innerHTML='<i class="bi bi-check2 me-1"></i>Sent!';btn.style.background='linear-gradient(135deg,#28a745,#20c997)';setTimeout(()=>MessagesModule.load(),800);}
        else{btn.disabled=false;btn.innerHTML='<i class="bi bi-send-fill"></i> Send Reply';alert('Failed to send reply.');}
    },
    async clearReply(id) { if(!confirm('Remove the admin reply?')) return; await fetch(API_URL+'?action=reply_message',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,reply:'',admin:'Admin'})}); await MessagesModule.load(); }
};

document.addEventListener('DOMContentLoaded', function () {

    // Wire up Online Requests link
    var reqLink = document.getElementById('onlineRequestsLink');
    if (reqLink) {
        reqLink.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            document.querySelectorAll('.sidebar-nav .nav-link,.sidebar-nav .nav-sub-link,.dropdown-toggle-link').forEach(l=>l.classList.remove('active'));
            reqLink.classList.add('active');
            document.getElementById('pageTitle').textContent = 'Online Booking Requests';
            document.getElementById('contentArea').innerHTML = OnlineRequestsModule.render();
            sessionStorage.setItem('kl_active_module', 'online_requests');
            if(window.innerWidth<=992){document.getElementById('sidebar')?.classList.remove('show');document.getElementById('sidebarOverlay')?.classList.remove('active');}
            window.scrollTo(0,0);
        });
    }

    if (window.LodgeApp && LodgeApp.modules) {
        LodgeApp.modules['online_requests'] = { title:'Online Booking Requests', module:OnlineRequestsModule };
    }

    DB.startAutoSync(60000);

    // Load admin name
    (async function() {
        try {
            const res=await fetch(API_URL+'?action=get_admin'), json=await res.json();
            if(json.success&&json.admin){
                const name=json.admin.name||'Admin', mobile=json.admin.mobile||'', firstName=name.split(' ')[0];
                const e1=document.getElementById('adminNameDisplay'), e2=document.getElementById('adminNameFull'), e3=document.getElementById('adminMobileDisplay');
                if(e1)e1.textContent=firstName; if(e2)e2.textContent=name; if(e3)e3.textContent=mobile;
            }
        } catch(e){}
    })();

    // Navigate patch — push/pull MySQL on every navigation
    if (window.LodgeApp) {
        const _orig = LodgeApp.navigate.bind(LodgeApp);
        LodgeApp.navigate = async function(moduleName) {
            await DB.pushToMySQL();
            await DB.pullFromMySQL();
            _orig(moduleName);
        };
    }

    // Wire up Messages link
    const messagesLink = document.getElementById('messagesLink');
    if (messagesLink) {
        messagesLink.addEventListener('click', async function(e) {
            e.preventDefault(); e.stopPropagation();
            document.querySelectorAll('.sidebar-nav .nav-link,.sidebar-nav .nav-sub-link,.dropdown-toggle-link').forEach(l=>l.classList.remove('active'));
            messagesLink.classList.add('active');
            document.getElementById('pageTitle').textContent = 'Contact Messages';
            document.getElementById('contentArea').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning"></div><p class="mt-3 text-muted">Loading messages...</p></div>';
            await MessagesModule.load();
            sessionStorage.setItem('kl_active_module', 'messages');
            if(window.innerWidth<=992){document.getElementById('sidebar')?.classList.remove('show');document.getElementById('sidebarOverlay')?.classList.remove('active');}
            window.scrollTo(0,0);
        });
    }

    // Messages badge updater
    async function updateMsgBadge() {
        try {
            const res=await fetch(API_URL+'?action=get_messages'), json=await res.json();
            if(json.success){
                const unread=json.messages.filter(m=>m.status==='New').length;
                const badge=document.getElementById('msgBadge');
                if(badge){badge.textContent=unread;badge.classList.toggle('show',unread>0);}
                const topBadge=document.getElementById('msgTopbarBadge');
                if(topBadge){topBadge.textContent=unread;topBadge.style.display=unread>0?'block':'none';}
                const bell=document.getElementById('msgBellBtn');
                if(bell){bell.style.borderColor=unread>0?'#dc3545':'';bell.style.color=unread>0?'#dc3545':'';}
            }
        } catch(e){}
    }
    updateMsgBadge();
    setInterval(updateMsgBadge, 30000);

    // Request badge updater
    function updateBadge(){DB._updateRequestBadge();}
    updateBadge();
    setInterval(updateBadge, 5000);

});
</script>
</body>
</html>
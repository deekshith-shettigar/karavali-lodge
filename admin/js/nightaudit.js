/* ==========================================
   Night Audit Module
   ========================================== */
const NightAuditModule = {
    render() {
        const audits = DB.getAll(DB.NIGHT_AUDITS);
        const todayAudit = audits.find(a => a.date === Utils.today());
        const rooms = DB.getAll(DB.ROOMS);
        const checkins = DB.getAll(DB.CHECKINS);
        const activeCheckins = checkins.filter(c => c.status === 'Checked-In');
        const bills = DB.getAll(DB.BILLS);
        const todayBills = bills.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today()));
        const unpaidBills = bills.filter(b => b.status === 'Unpaid');
        const roomServiceOrders = DB.getAll(DB.ROOM_SERVICE);
        const todayOrders = roomServiceOrders.filter(o => o.createdAt && o.createdAt.startsWith(Utils.today()));
        const guestServices = DB.getAll(DB.GUEST_SERVICES);
        const todayGuestServices = guestServices.filter(gs => gs.createdAt && gs.createdAt.startsWith(Utils.today()));
        const bookings = DB.getAll(DB.BOOKINGS);
        const todayCheckIns = checkins.filter(c => c.checkInDate === Utils.today());
        const todayCheckOuts = checkins.filter(c => c.status === 'Checked-Out' && c.checkOutDate <= Utils.today());

        const occupiedRooms = rooms.filter(r => r.status === 'Occupied').length;
        const reservedRooms = rooms.filter(r => r.status === 'Reserved').length;
        const cleaningRooms = rooms.filter(r => r.status === 'Cleaning').length;
        const maintenanceRooms = rooms.filter(r => r.status === 'Maintenance').length;
        const availableRooms = rooms.filter(r => (r.status || 'Available') === 'Available').length;
        const totalRooms = rooms.length || 1;
        const occupancyRate = ((occupiedRooms / totalRooms) * 100).toFixed(1);

        const todayRoomRevenue = todayBills.reduce((sum, b) => sum + (parseFloat(b.roomCharges) || 0), 0);
        const todayServiceRevenue = todayOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);
        const todayAmenityRevenue = todayGuestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);
        const todayTax = todayBills.reduce((sum, b) => sum + (parseFloat(b.tax) || 0), 0);
        const todayTotalRevenue = todayBills.reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const todayPaidAmount = todayBills.filter(b => b.status === 'Paid').reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const todayUnpaidAmount = todayBills.filter(b => b.status === 'Unpaid').reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);
        const totalOutstanding = unpaidBills.reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);

        // Discrepancy check: rooms marked Occupied but no active check-in, or check-in exists but room not Occupied
        const discrepancies = [];
        rooms.forEach(r => {
            const ci = activeCheckins.find(c => c.roomId === r.id);
            if (r.status === 'Occupied' && !ci) {
                discrepancies.push({ room: r.roomNumber, issue: 'Room marked Occupied but no active check-in found', type: 'warning' });
            }
            if (ci && r.status !== 'Occupied') {
                discrepancies.push({ room: r.roomNumber, issue: `Active check-in (${ci.guestName}) but room status is "${r.status}"`, type: 'danger' });
            }
        });

        return `
            <!-- Audit Header -->
            <div class="audit-summary mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <h4><i class="bi bi-moon-stars me-2"></i>Night Audit - ${Utils.formatDate(Utils.today())}</h4>
                        <p class="mb-0 opacity-75">Daily reconciliation, verification & financial closing</p>
                    </div>
                    <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
                        <button class="btn btn-outline-light" onclick="NightAuditModule.generateDailySummary()">
                            <i class="bi bi-file-earmark-text me-1"></i>Generate Daily Summary
                        </button>
                        ${todayAudit ?
                            `<span class="badge bg-success fs-6 py-2 px-3"><i class="bi bi-check-circle me-1"></i>Audit Closed at ${new Date(todayAudit.auditTime).toLocaleTimeString()}</span>` :
                            `<button class="btn btn-light btn-lg" onclick="NightAuditModule.runAudit()">
                                <i class="bi bi-play-circle me-2"></i>Run Night Audit
                            </button>`
                        }
                    </div>
                </div>
            </div>

            <!-- Key Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card" style="border-left:4px solid #28a745">
                        <div class="stat-value">${occupancyRate}%</div>
                        <div class="stat-label">Occupancy Rate</div>
                        <small class="text-muted">${occupiedRooms} of ${totalRooms} rooms</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card" style="border-left:4px solid #3498db">
                        <div class="stat-value">${Utils.formatCurrency(todayTotalRevenue)}</div>
                        <div class="stat-label">Today's Revenue</div>
                        <small class="text-muted">${todayBills.length} bill(s)</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card" style="border-left:4px solid #e67e22">
                        <div class="stat-value">${Utils.formatCurrency(totalOutstanding)}</div>
                        <div class="stat-label">Total Outstanding</div>
                        <small class="text-muted">${unpaidBills.length} unpaid bill(s)</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card" style="border-left:4px solid ${discrepancies.length > 0 ? '#dc3545' : '#28a745'}">
                        <div class="stat-value">${discrepancies.length}</div>
                        <div class="stat-label">Discrepancies</div>
                        <small class="text-muted">${discrepancies.length === 0 ? 'All clear' : 'Needs review'}</small>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- LEFT COLUMN -->
                <div class="col-lg-6">
                    <!-- 1. Room Occupancy Verification -->
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-building me-2"></i>Room Occupancy Verification</span>
                            <span class="badge bg-light text-dark">${occupiedRooms} occupied</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:320px;overflow-y:auto">
                                <table class="table data-table mb-0">
                                    <thead style="position:sticky;top:0;z-index:1">
                                        <tr>
                                            <th>Room</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Guest</th>
                                            <th>Check-In</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${rooms.map(r => {
                                            const ci = activeCheckins.find(c => c.roomId === r.id);
                                            const hasIssue = discrepancies.some(d => d.room === r.roomNumber);
                                            return `
                                            <tr style="${hasIssue ? 'background:#fff3cd' : ''}">
                                                <td><strong>${Utils.escapeHtml(r.roomNumber)}</strong> ${hasIssue ? '<i class="bi bi-exclamation-triangle text-warning"></i>' : ''}</td>
                                                <td><small>${Utils.escapeHtml(r.roomType)}</small></td>
                                                <td>${Utils.statusBadge(r.status || 'Available')}</td>
                                                <td>${ci ? Utils.escapeHtml(ci.guestName) : '<span class="text-muted">-</span>'}</td>
                                                <td>${ci ? Utils.formatDate(ci.checkInDate) : '<span class="text-muted">-</span>'}</td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                            <!-- Room Status Summary Bar -->
                            <div class="p-3 border-top" style="background:#fafafa">
                                <div class="d-flex gap-3 flex-wrap">
                                    <small><span class="badge bg-success">●</span> Available: <strong>${availableRooms}</strong></small>
                                    <small><span class="badge bg-danger">●</span> Occupied: <strong>${occupiedRooms}</strong></small>
                                    <small><span class="badge bg-warning text-dark">●</span> Reserved: <strong>${reservedRooms}</strong></small>
                                    <small><span class="badge bg-info">●</span> Cleaning: <strong>${cleaningRooms}</strong></small>
                                    <small><span class="badge bg-secondary">●</span> Maintenance: <strong>${maintenanceRooms}</strong></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Discrepancy Alerts -->
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-shield-exclamation me-2"></i>Discrepancy Alerts</span>
                            ${discrepancies.length === 0 ?
                                '<span class="badge bg-success">All OK</span>' :
                                `<span class="badge bg-danger">${discrepancies.length} issue(s)</span>`}
                        </div>
                        <div class="card-body">
                            ${discrepancies.length === 0 ?
                                '<div class="text-center py-3"><i class="bi bi-check-circle text-success fs-2 d-block mb-2"></i><span class="text-muted">No discrepancies found. Room statuses match check-in records.</span></div>' :
                                `<div class="list-group list-group-flush">
                                    ${discrepancies.map(d => `
                                        <div class="list-group-item d-flex align-items-start gap-2 border-0 px-0">
                                            <i class="bi bi-exclamation-triangle-fill text-${d.type} mt-1"></i>
                                            <div>
                                                <strong>Room ${Utils.escapeHtml(d.room)}</strong>
                                                <div class="text-muted" style="font-size:0.85rem">${Utils.escapeHtml(d.issue)}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-warning" onclick="NightAuditModule.autoFixDiscrepancies()">
                                        <i class="bi bi-wrench me-1"></i>Auto-Fix Discrepancies
                                    </button>
                                </div>`
                            }
                        </div>
                    </div>

                    <!-- Daily Activity -->
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-activity me-2"></i>Today's Activity</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-3 rounded" style="background:#e8f5e9">
                                        <div style="font-size:1.3rem;font-weight:700;color:#2e7d32">${todayCheckIns.length}</div>
                                        <small class="text-muted">Check-Ins Today</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded" style="background:#fff3e0">
                                        <div style="font-size:1.3rem;font-weight:700;color:#e65100">${todayCheckOuts.length}</div>
                                        <small class="text-muted">Check-Outs Today</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded" style="background:#e3f2fd">
                                        <div style="font-size:1.3rem;font-weight:700;color:#1565c0">${bookings.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today())).length}</div>
                                        <small class="text-muted">New Bookings</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded" style="background:#fce4ec">
                                        <div style="font-size:1.3rem;font-weight:700;color:#c62828">${todayOrders.length + todayGuestServices.length}</div>
                                        <small class="text-muted">Service Orders</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-lg-6">
                    <!-- 3. Revenue Verification -->
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-currency-rupee me-2"></i>Revenue Verification</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="border-0"><i class="bi bi-house-door text-primary me-2"></i>Room Charges</td>
                                        <td class="border-0 text-end fw-bold">${Utils.formatCurrency(todayRoomRevenue)}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="bi bi-cup-hot text-warning me-2"></i>Room Service Revenue</td>
                                        <td class="text-end fw-bold">${Utils.formatCurrency(todayServiceRevenue)}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="bi bi-stars text-info me-2"></i>Amenity / Extra Services</td>
                                        <td class="text-end fw-bold">${Utils.formatCurrency(todayAmenityRevenue)}</td>
                                    </tr>
                                    <tr style="background:#f8f9fa">
                                        <td><strong>Subtotal</strong></td>
                                        <td class="text-end fw-bold">${Utils.formatCurrency(todayRoomRevenue + todayServiceRevenue + todayAmenityRevenue)}</td>
                                    </tr>
                                    <tr>
                                        <td><i class="bi bi-percent text-secondary me-2"></i>Tax (GST)</td>
                                        <td class="text-end fw-bold">${Utils.formatCurrency(todayTax)}</td>
                                    </tr>
                                    <tr class="table-dark">
                                        <td><strong>Total Revenue</strong></td>
                                        <td class="text-end fw-bold">${Utils.formatCurrency(todayRoomRevenue + todayServiceRevenue + todayAmenityRevenue + todayTax)}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <hr>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-success fw-bold">${Utils.formatCurrency(todayPaidAmount)}</div>
                                    <small class="text-muted">Collected</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger fw-bold">${Utils.formatCurrency(todayUnpaidAmount)}</div>
                                    <small class="text-muted">Today Unpaid</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning fw-bold">${Utils.formatCurrency(totalOutstanding)}</div>
                                    <small class="text-muted">Total Outstanding</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Today's Transactions -->
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-receipt me-2"></i>Today's Transactions</span>
                            <span class="badge bg-light text-dark">${todayBills.length} bill(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:250px;overflow-y:auto">
                                <table class="table data-table mb-0">
                                    <thead style="position:sticky;top:0;z-index:1">
                                        <tr><th>Bill No</th><th>Guest</th><th>Room</th><th>Amount</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        ${todayBills.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No transactions today</td></tr>' :
                                        todayBills.map(b => {
                                            const room = DB.getById(DB.ROOMS, b.roomId);
                                            return `
                                            <tr>
                                                <td><small>${Utils.escapeHtml(b.billNo)}</small></td>
                                                <td>${Utils.escapeHtml(b.guestName)}</td>
                                                <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                <td>${Utils.formatCurrency(b.totalAmount)}</td>
                                                <td>${Utils.statusBadge(b.status)}</td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Audit History -->
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-clock-history me-2"></i>Audit History</span>
                            <span class="badge bg-light text-dark">${audits.length} audit(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:250px;overflow-y:auto">
                                <table class="table data-table mb-0">
                                    <thead style="position:sticky;top:0;z-index:1">
                                        <tr><th>Date</th><th>Occupancy</th><th>Revenue</th><th>Discrepancies</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        ${audits.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No audits completed yet</td></tr>' :
                                        audits.sort((a,b) => new Date(b.date) - new Date(a.date)).slice(0, 20).map(a => `
                                            <tr>
                                                <td>${Utils.formatDate(a.date)}</td>
                                                <td>${a.occupancyRate}%</td>
                                                <td>${Utils.formatCurrency(a.totalRevenue)}</td>
                                                <td>${(a.discrepancies || 0) > 0 ? `<span class="badge bg-warning text-dark">${a.discrepancies}</span>` : '<span class="badge bg-success">0</span>'}</td>
                                                <td><button class="btn btn-sm btn-outline-primary" onclick="NightAuditModule.viewAuditSummary('${a.id}')"><i class="bi bi-eye"></i></button></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Summary Modal -->
            <div class="modal fade" id="auditSummaryModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Daily Audit Summary</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="auditSummaryContent"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="NightAuditModule.printSummary()"><i class="bi bi-printer me-1"></i>Print</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    runAudit() {
        // Guard against double-run — check before the confirm dialog
        const existingAudit = DB.query(DB.NIGHT_AUDITS, a => a.date === Utils.today())[0];
        if (existingAudit) {
            Utils.showToast('Night audit for today has already been run.', 'warning');
            return;
        }

        if (!Utils.confirmAction('Run night audit for today? This will close daily accounts and generate a summary.')) return;

        const rooms = DB.getAll(DB.ROOMS);
        const checkins = DB.getAll(DB.CHECKINS);
        const activeCheckins = checkins.filter(c => c.status === 'Checked-In');
        const bills = DB.getAll(DB.BILLS);
        const todayBills = bills.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today()));
        const unpaidBills = bills.filter(b => b.status === 'Unpaid');
        const roomServiceOrders = DB.getAll(DB.ROOM_SERVICE).filter(o => o.createdAt && o.createdAt.startsWith(Utils.today()));
        const guestServices = DB.getAll(DB.GUEST_SERVICES).filter(gs => gs.createdAt && gs.createdAt.startsWith(Utils.today()));
        const bookings = DB.getAll(DB.BOOKINGS);
        const todayCheckIns = checkins.filter(c => c.checkInDate === Utils.today());
        const todayCheckOuts = checkins.filter(c => c.status === 'Checked-Out' && c.checkOutDate <= Utils.today());

        const operationalRooms = rooms.filter(r => !['Maintenance','Out of Order'].includes(r.status || 'Available'));
        const occupiedRooms = rooms.filter(r => r.status === 'Occupied').length;
        const reservedRooms = rooms.filter(r => r.status === 'Reserved').length;
        const availableRooms = operationalRooms.filter(r => (r.status || 'Available') === 'Available').length;
        const totalRooms = operationalRooms.length || 1;
        const occupancyRate = ((occupiedRooms / totalRooms) * 100).toFixed(1);

        const roomRevenue = todayBills.reduce((sum, b) => sum + (parseFloat(b.roomCharges) || 0), 0);
        const serviceRevenue = roomServiceOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);
        const amenityRevenue = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);
        const taxCollected = todayBills.reduce((sum, b) => sum + (parseFloat(b.tax) || 0), 0);
        const totalRevenue = todayBills.reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const paidAmount = todayBills.filter(b => b.status === 'Paid').reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const unpaidAmount = todayBills.filter(b => b.status === 'Unpaid').reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);
        const totalOutstanding = unpaidBills.reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);

        // Discrepancies
        let discrepancyCount = 0;
        const discrepancyDetails = [];
        rooms.forEach(r => {
            const ci = activeCheckins.find(c => c.roomId === r.id);
            if (r.status === 'Occupied' && !ci) {
                discrepancyCount++;
                discrepancyDetails.push(`Room ${r.roomNumber}: Occupied but no check-in`);
            }
            if (ci && r.status !== 'Occupied') {
                discrepancyCount++;
                discrepancyDetails.push(`Room ${r.roomNumber}: Check-in (${ci.guestName}) but status "${r.status}"`);
            }
        });

        const audit = {
            date: Utils.today(),
            occupiedRooms,
            reservedRooms,
            availableRooms,
            totalRooms,
            occupancyRate,
            roomRevenue,
            serviceRevenue,
            amenityRevenue,
            taxCollected,
            totalRevenue: roomRevenue + serviceRevenue + amenityRevenue + taxCollected,
            paidAmount,
            unpaidAmount,
            totalOutstanding,
            billCount: todayBills.length,
            unpaidBillCount: todayBills.filter(b => b.status === 'Unpaid').length,
            todayCheckIns: todayCheckIns.length,
            todayCheckOuts: todayCheckOuts.length,
            newBookings: bookings.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today())).length,
            discrepancies: discrepancyCount,
            discrepancyDetails,
            auditTime: new Date().toISOString(),
            status: 'Closed'
        };

        DB.add(DB.NIGHT_AUDITS, audit);
        Utils.showToast('Night audit completed — daily accounts closed', 'success');
        LodgeApp.navigate('nightaudit');
    },

    autoFixDiscrepancies() {
        if (!Utils.confirmAction('Auto-fix will update room statuses to match check-in records. Proceed?')) return;

        const rooms = DB.getAll(DB.ROOMS);
        const activeCheckins = DB.getAll(DB.CHECKINS).filter(c => c.status === 'Checked-In');
        let fixed = 0;

        rooms.forEach(r => {
            const ci = activeCheckins.find(c => c.roomId === r.id);
            if (r.status === 'Occupied' && !ci) {
                DB.update(DB.ROOMS, r.id, { status: 'Available' });
                fixed++;
            }
            if (ci && r.status !== 'Occupied') {
                DB.update(DB.ROOMS, r.id, { status: 'Occupied' });
                fixed++;
            }
        });

        Utils.showToast(`Fixed ${fixed} discrepancy(ies)`, 'success');
        LodgeApp.navigate('nightaudit');
    },

    generateDailySummary() {
        const rooms = DB.getAll(DB.ROOMS);
        const checkins = DB.getAll(DB.CHECKINS);
        const activeCheckins = checkins.filter(c => c.status === 'Checked-In');
        const bills = DB.getAll(DB.BILLS);
        const todayBills = bills.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today()));
        const unpaidBills = bills.filter(b => b.status === 'Unpaid');
        const roomServiceOrders = DB.getAll(DB.ROOM_SERVICE).filter(o => o.createdAt && o.createdAt.startsWith(Utils.today()));
        const guestServices = DB.getAll(DB.GUEST_SERVICES).filter(gs => gs.createdAt && gs.createdAt.startsWith(Utils.today()));
        const bookings = DB.getAll(DB.BOOKINGS);
        const todayCheckIns = checkins.filter(c => c.checkInDate === Utils.today());
        const todayCheckOuts = checkins.filter(c => c.status === 'Checked-Out' && c.checkOutDate <= Utils.today());

        const operationalRooms = rooms.filter(r => !['Maintenance','Out of Order'].includes(r.status || 'Available'));
        const occupiedRooms = rooms.filter(r => r.status === 'Occupied').length;
        const reservedRooms = rooms.filter(r => r.status === 'Reserved').length;
        const availableRooms = operationalRooms.filter(r => (r.status || 'Available') === 'Available').length;
        const totalRooms = operationalRooms.length || 1;
        const occupancyRate = ((occupiedRooms / totalRooms) * 100).toFixed(1);

        const roomRevenue = todayBills.reduce((sum, b) => sum + (parseFloat(b.roomCharges) || 0), 0);
        const serviceRevenue = roomServiceOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);
        const amenityRevenue = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);
        const taxCollected = todayBills.reduce((sum, b) => sum + (parseFloat(b.tax) || 0), 0);
        const paidAmount = todayBills.filter(b => b.status === 'Paid').reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const unpaidAmount = todayBills.filter(b => b.status === 'Unpaid').reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);
        const totalOutstanding = unpaidBills.reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);

        let discrepancyCount = 0;
        const discrepancyDetails = [];
        rooms.forEach(r => {
            const ci = activeCheckins.find(c => c.roomId === r.id);
            if (r.status === 'Occupied' && !ci) { discrepancyCount++; discrepancyDetails.push(`Room ${r.roomNumber}: Occupied but no check-in`); }
            if (ci && r.status !== 'Occupied') { discrepancyCount++; discrepancyDetails.push(`Room ${r.roomNumber}: Check-in (${ci.guestName}) but status "${r.status}"`); }
        });

        // Check if audit already closed today
        const audits = DB.getAll(DB.NIGHT_AUDITS);
        const todayAudit = audits.find(a => a.date === Utils.today());

        this._renderSummaryModal({
            date: Utils.today(),
            occupiedRooms, reservedRooms, availableRooms, totalRooms, occupancyRate,
            roomRevenue, serviceRevenue, amenityRevenue, taxCollected,
            totalRevenue: roomRevenue + serviceRevenue + amenityRevenue + taxCollected,
            paidAmount, unpaidAmount, totalOutstanding,
            billCount: todayBills.length,
            unpaidBillCount: todayBills.filter(b => b.status === 'Unpaid').length,
            todayCheckIns: todayCheckIns.length,
            todayCheckOuts: todayCheckOuts.length,
            newBookings: bookings.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today())).length,
            discrepancies: discrepancyCount,
            discrepancyDetails,
            auditTime: todayAudit ? todayAudit.auditTime : new Date().toISOString(),
            status: todayAudit ? 'Closed' : 'Live'
        });
    },

    viewAuditSummary(id) {
        const audit = DB.getById(DB.NIGHT_AUDITS, id);
        if (audit) {
            this._renderSummaryModal(audit);
        }
    },

    printSummary() {
        const content = document.getElementById('auditSummaryContent').innerHTML;
        const win = window.open('', '_blank', 'width=800,height=600');
        win.document.write(`<!DOCTYPE html><html><head><title>Daily Audit Summary</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>body{padding:20px;font-family:Georgia,serif;color:#333} .table{font-size:12px} h4{color:#3B1A0A} h6{color:#C4943A;border-bottom:2px solid #C4943A;padding-bottom:4px}</style>
            </head><body>${content}</body></html>`);
        win.document.close();
        win.onload = function() { win.print(); };
    },

    _renderSummaryModal(a) {
        const html = `
            <div style="font-family:Georgia,serif;color:#333">
                <div class="text-center mb-4">
                    <h4 style="color:var(--primary);margin-bottom:2px">Karavali Lodge</h4>
                    <p class="text-muted mb-1">Night Audit — Daily Summary Report</p>
                    <h5>${Utils.formatDate(a.date)}</h5>
                    <small class="text-muted">Closed at ${new Date(a.auditTime).toLocaleString()}</small>
                </div>
                <hr>

                <div class="row mb-4">
                    <div class="col-6">
                        <h6 style="color:var(--accent);border-bottom:2px solid var(--accent);padding-bottom:4px">
                            <i class="bi bi-building me-1"></i>Room Occupancy
                        </h6>
                        <table class="table table-sm mb-0">
                            <tr><td>Total Rooms</td><td class="text-end fw-bold">${a.totalRooms}</td></tr>
                            <tr><td>Occupied</td><td class="text-end fw-bold text-danger">${a.occupiedRooms}</td></tr>
                            <tr><td>Reserved</td><td class="text-end fw-bold text-warning">${a.reservedRooms || 0}</td></tr>
                            <tr><td>Available</td><td class="text-end fw-bold text-success">${a.availableRooms || 0}</td></tr>
                            <tr class="table-light"><td><strong>Occupancy Rate</strong></td><td class="text-end fw-bold">${a.occupancyRate}%</td></tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <h6 style="color:var(--accent);border-bottom:2px solid var(--accent);padding-bottom:4px">
                            <i class="bi bi-activity me-1"></i>Daily Activity
                        </h6>
                        <table class="table table-sm mb-0">
                            <tr><td>Check-Ins</td><td class="text-end fw-bold">${a.todayCheckIns || 0}</td></tr>
                            <tr><td>Check-Outs</td><td class="text-end fw-bold">${a.todayCheckOuts || 0}</td></tr>
                            <tr><td>New Bookings</td><td class="text-end fw-bold">${a.newBookings || 0}</td></tr>
                            <tr><td>Bills Generated</td><td class="text-end fw-bold">${a.billCount}</td></tr>
                            <tr><td>Unpaid Bills</td><td class="text-end fw-bold text-danger">${a.unpaidBillCount || 0}</td></tr>
                        </table>
                    </div>
                </div>

                <h6 style="color:var(--accent);border-bottom:2px solid var(--accent);padding-bottom:4px">
                    <i class="bi bi-currency-rupee me-1"></i>Revenue Summary
                </h6>
                <table class="table table-sm mb-3">
                    <tr><td>Room Charges</td><td class="text-end fw-bold">${Utils.formatCurrency(a.roomRevenue)}</td></tr>
                    <tr><td>Room Service</td><td class="text-end fw-bold">${Utils.formatCurrency(a.serviceRevenue)}</td></tr>
                    <tr><td>Amenity / Extra Services</td><td class="text-end fw-bold">${Utils.formatCurrency(a.amenityRevenue || 0)}</td></tr>
                    <tr><td>Tax (GST)</td><td class="text-end fw-bold">${Utils.formatCurrency(a.taxCollected || 0)}</td></tr>
                    <tr class="table-dark"><td><strong>Total Revenue</strong></td><td class="text-end fw-bold">${Utils.formatCurrency(a.totalRevenue)}</td></tr>
                </table>

                <div class="row mb-4">
                    <div class="col-4 text-center p-3 rounded" style="background:#e8f5e9">
                        <div class="fw-bold text-success">${Utils.formatCurrency(a.paidAmount || 0)}</div>
                        <small class="text-muted">Collected</small>
                    </div>
                    <div class="col-4 text-center p-3 rounded" style="background:#fff3e0">
                        <div class="fw-bold text-danger">${Utils.formatCurrency(a.unpaidAmount || 0)}</div>
                        <small class="text-muted">Today Unpaid</small>
                    </div>
                    <div class="col-4 text-center p-3 rounded" style="background:#fce4ec">
                        <div class="fw-bold text-warning">${Utils.formatCurrency(a.totalOutstanding || 0)}</div>
                        <small class="text-muted">Total Outstanding</small>
                    </div>
                </div>

                <h6 style="color:var(--accent);border-bottom:2px solid var(--accent);padding-bottom:4px">
                    <i class="bi bi-shield-check me-1"></i>Verification Status
                </h6>
                <div class="p-3 rounded mb-3" style="background:${(a.discrepancies || 0) === 0 ? '#e8f5e9' : '#fff3cd'}">
                    ${(a.discrepancies || 0) === 0 ?
                        '<i class="bi bi-check-circle-fill text-success me-2"></i><strong>All Clear</strong> — Room occupancy matches check-in records.' :
                        `<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><strong>${a.discrepancies} Discrepancy(ies) Found</strong>
                        <ul class="mb-0 mt-2">${(a.discrepancyDetails || []).map(d => `<li>${Utils.escapeHtml(d)}</li>`).join('')}</ul>`
                    }
                </div>

                <div class="text-center text-muted mt-4" style="font-size:0.8rem">
                    <i class="bi bi-lock me-1"></i>Audit closed and locked — ${new Date(a.auditTime).toLocaleString()}
                </div>
            </div>
        `;

        document.getElementById('auditSummaryContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('auditSummaryModal')).show();
    }
};
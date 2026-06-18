/* ==========================================
   Dashboard Module
   ========================================== */
const DashboardModule = {
    render() {
        const rooms = DB.getAll(DB.ROOMS);
        const bookings = DB.getAll(DB.BOOKINGS);
        const guests = DB.getAll(DB.GUESTS);
        const checkins = DB.getAll(DB.CHECKINS);
        const bills = DB.getAll(DB.BILLS);
        const activeCheckins = checkins.filter(c => c.status === 'Checked-In');

        const operationalRooms = rooms.filter(r => !['Maintenance','Out of Order'].includes(r.status));
        const occupiedRooms  = rooms.filter(r => r.status === 'Occupied').length;
        const availableRooms = operationalRooms.filter(r => (r.status || 'Available') === 'Available').length;
        const totalRevenue = bills.reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const todayBookings = bookings.filter(b => b.createdAt && b.createdAt.startsWith(Utils.today()));
        const pendingBookings = bookings.filter(b => b.status === 'Pending' || b.status === 'Confirmed');

        return `
            <!-- Statistics Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${rooms.length}</div>
                                <div class="stat-label">Total Rooms</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-door-open"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#28a745">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${availableRooms}</div>
                                <div class="stat-label">Available</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#dc3545">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${occupiedRooms}</div>
                                <div class="stat-label">Occupied</div>
                            </div>
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#9b59b6">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${Utils.formatCurrency(totalRevenue)}</div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-currency-rupee"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#e67e22">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${guests.length}</div>
                                <div class="stat-label">Total Guests</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#2ecc71">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${activeCheckins.length}</div>
                                <div class="stat-label">Checked-In Now</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-box-arrow-in-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#3498db">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bookings.length}</div>
                                <div class="stat-label">Total Bookings</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="border-left-color:#f39c12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${pendingBookings.length}</div>
                                <div class="stat-label">Pending Arrivals</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h6 class="text-muted mb-3"><i class="bi bi-lightning me-1"></i>Quick Actions</h6>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('booking');setTimeout(()=>BookingModule.showForm(),300);return false;">
                        <i class="bi bi-calendar-plus d-block"></i>
                        <p>New Booking</p>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('checkin');return false;">
                        <i class="bi bi-box-arrow-in-right d-block"></i>
                        <p>Check-In</p>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('rooms');setTimeout(()=>RoomModule.showForm(),300);return false;">
                        <i class="bi bi-plus-square d-block"></i>
                        <p>Add Room</p>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('roomservice');return false;">
                        <i class="bi bi-cup-straw d-block"></i>
                        <p>Room Service</p>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('billing');return false;">
                        <i class="bi bi-receipt d-block"></i>
                        <p>Billing</p>
                    </a>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="LodgeApp.navigate('reports');return false;">
                        <i class="bi bi-graph-up d-block"></i>
                        <p>Reports</p>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Room Overview -->
                <div class="col-lg-6">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-grid me-2"></i>Room Status</span>
                            <a href="#" class="btn btn-sm btn-outline-light" onclick="LodgeApp.navigate('rooms');return false;">View All</a>
                        </div>
                        <div class="card-body">
                            ${rooms.length === 0 ?
                                '<div class="empty-state"><i class="bi bi-door-closed d-block"></i>No rooms. Add rooms to get started.</div>' :
                                `<div class="room-grid">${rooms.slice(0, 12).map(r => `
                                    <div class="room-cell ${(r.status || 'Available').toLowerCase()}">
                                        <div class="room-number">${Utils.escapeHtml(r.roomNumber)}</div>
                                        <div class="room-type">${Utils.escapeHtml(r.roomType)}</div>
                                        <div class="mt-1">${Utils.statusBadge(r.status || 'Available')}</div>
                                    </div>
                                `).join('')}</div>
                                ${rooms.length > 12 ? `<div class="text-center mt-2"><small class="text-muted">+${rooms.length - 12} more rooms</small></div>` : ''}`
                            }
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-6">
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-clock-history me-2"></i>Recent Bookings</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr><th>Guest</th><th>Room</th><th>Date</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        ${bookings.length === 0 ? '<tr><td colspan="4" class="text-center py-3 text-muted">No bookings yet</td></tr>' :
                                        bookings.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 5).map(b => {
                                            const room = DB.getById(DB.ROOMS, b.roomId);
                                            return `<tr>
                                                <td>${Utils.escapeHtml(b.guestName)}</td>
                                                <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                <td>${Utils.formatDate(b.checkIn)}</td>
                                                <td>${Utils.statusBadge(b.status)}</td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-people me-2"></i>Currently Checked-In</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr><th>Guest</th><th>Room</th><th>Since</th></tr>
                                    </thead>
                                    <tbody>
                                        ${activeCheckins.length === 0 ? '<tr><td colspan="3" class="text-center py-3 text-muted">No active guests</td></tr>' :
                                        activeCheckins.slice(0, 5).map(c => {
                                            const room = DB.getById(DB.ROOMS, c.roomId);
                                            return `<tr>
                                                <td>${Utils.escapeHtml(c.guestName)}</td>
                                                <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                <td>${Utils.formatDate(c.checkInDate)}</td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
};
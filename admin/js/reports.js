/* ==========================================
   Lodge Reports Module — with Charts & Graphs
   ========================================== */
const ReportsModule = {
    _charts: {},

    // Destroy any existing chart on the given canvas id
    _destroyChart(id) {
        if (this._charts[id]) { this._charts[id].destroy(); delete this._charts[id]; }
    },

    // Enhanced palette — brown/gold tones matching the logo
    _colors: ['#3B1A0A','#C4943A','#5C2E15','#D4AD5E','#8B5E3C','#A67C52','#6B3A2A','#E8C97A','#4A2512','#B8860B','#7B4B2A','#D2A050'],
    _softColors: ['#C4943A','#5C2E15','#D4AD5E','#8B5E3C','#A67C52','#E8C97A','#6B3A2A','#B8860B','#4A2512','#D2A050','#7B4B2A','#3B1A0A'],
    _alpha(hex, a) { const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16); return `rgba(${r},${g},${b},${a})`; },

    // Create vertical gradient for line/bar fills
    _gradient(ctx, hex1, hex2) {
        const g = ctx.createLinearGradient(0, 0, 0, 400);
        g.addColorStop(0, this._alpha(hex1, 0.5));
        g.addColorStop(1, this._alpha(hex2 || hex1, 0.02));
        return g;
    },

    // Shared chart defaults
    _defaultOptions(extra = {}) {
        return {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 800, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, font: { size: 12, family: "'Segoe UI', sans-serif" }, color: '#3B1A0A' }
                },
                tooltip: {
                    backgroundColor: '#3B1A0A',
                    titleColor: '#D4AD5E',
                    bodyColor: '#fff',
                    borderColor: '#C4943A',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    titleFont: { weight: '600', size: 13 },
                    bodyFont: { size: 12 },
                    displayColors: true,
                    boxPadding: 4
                }
            },
            ...extra
        };
    },

    // Shared scale defaults for bar/line charts
    _scales(yOpts = {}, xOpts = {}) {
        return {
            y: { beginAtZero: true, grid: { color: 'rgba(59,26,10,0.06)', drawBorder: false }, ticks: { color: '#5C2E15', font: { size: 11 }, padding: 8 }, border: { display: false }, ...yOpts },
            x: { grid: { display: false }, ticks: { color: '#5C2E15', font: { size: 11 }, maxRotation: 45 }, border: { display: false }, ...xOpts }
        };
    },

    render() {
        return `
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('occupancy');return false;">
                        <i class="bi bi-building d-block"></i>
                        <p>Occupancy Report</p>
                    </a>
                </div>
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('revenue');return false;">
                        <i class="bi bi-currency-rupee d-block"></i>
                        <p>Revenue Report</p>
                    </a>
                </div>
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('guest');return false;">
                        <i class="bi bi-people d-block"></i>
                        <p>Guest Report</p>
                    </a>
                </div>
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('availability');return false;">
                        <i class="bi bi-door-open d-block"></i>
                        <p>Room Availability</p>
                    </a>
                </div>
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('checkinout');return false;">
                        <i class="bi bi-arrow-left-right d-block"></i>
                        <p>Check-In/Out</p>
                    </a>
                </div>
                <div class="col-md-4 col-lg-2">
                    <a href="#" class="quick-action d-block" onclick="ReportsModule.showReport('services');return false;">
                        <i class="bi bi-stars d-block"></i>
                        <p>Services Report</p>
                    </a>
                </div>
            </div>

            <div id="reportContent">
                <div class="module-card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-graph-up" style="font-size:3rem;color:#ccc"></i>
                        <p class="text-muted mt-2">Select a report type above to generate</p>
                    </div>
                </div>
            </div>
        `;
    },

    showReport(type) {
        // Destroy all previous charts
        Object.keys(this._charts).forEach(id => this._destroyChart(id));
        const container = document.getElementById('reportContent');
        switch (type) {
            case 'occupancy': container.innerHTML = this.occupancyReport(); this._renderOccupancyCharts(); break;
            case 'revenue': container.innerHTML = this.revenueReport(); this._renderRevenueCharts(); break;
            case 'guest': container.innerHTML = this.guestReport(); this._renderGuestCharts(); break;
            case 'availability': container.innerHTML = this.availabilityReport(); this._renderAvailabilityCharts(); break;
            case 'checkinout': container.innerHTML = this.checkInOutReport(); this._renderCheckinCharts(); break;
            case 'services': container.innerHTML = this.servicesReport(); this._renderServicesCharts(); break;
        }
    },

    /* ==========================================
       OCCUPANCY REPORT
       ========================================== */
    occupancyReport() {
        const rooms = DB.getAll(DB.ROOMS);
        const operationalRooms = rooms.filter(r => !['Maintenance','Out of Order'].includes(r.status || 'Available'));
        const total = operationalRooms.length || 1;
        const statuses = {};
        rooms.forEach(r => { const s = r.status || 'Available'; statuses[s] = (statuses[s] || 0) + 1; });

        const audits = DB.getAll(DB.NIGHT_AUDITS).sort((a,b) => new Date(a.date) - new Date(b.date)).slice(-30);

        return `
            <div class="module-card report-section" id="rpt-occupancy">
                <div class="card-header">
                    <span><i class="bi bi-building me-2"></i>Occupancy Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-occupancy')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-occupancy', 'Occupancy_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-5">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>Current Room Status</h6>
                                <canvas id="chartOccStatus"></canvas>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Occupancy by Room Type</h6>
                                <canvas id="chartOccType"></canvas>
                            </div>
                        </div>
                    </div>
                    ${audits.length > 0 ? `
                    <div class="chart-container mb-4">
                        <h6 class="chart-title"><i class="bi bi-graph-up me-1"></i>Occupancy Trend (Last ${audits.length} Days)</h6>
                        <canvas id="chartOccTrend"></canvas>
                    </div>` : ''}

                    <!-- Data Tables -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <h6>Room Status Summary</h6>
                            <table class="table table-sm">
                                <tbody>
                                    ${Object.entries(statuses).map(([status, count]) => `
                                        <tr class="align-middle">
                                            <td style="width:120px">${Utils.statusBadge(status)}</td>
                                            <td style="white-space:nowrap">${count} rooms</td>
                                            <td style="min-width:180px;width:40%">
                                                <div class="progress" style="height:14px;border-radius:7px">
                                                    <div class="progress-bar" style="width:${(count/total*100).toFixed(0)}%;border-radius:7px"></div>
                                                </div>
                                            </td>
                                            <td style="white-space:nowrap;font-weight:600">${(count/total*100).toFixed(1)}%</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Occupancy by Room Type</h6>
                            <table class="table table-sm">
                                <thead><tr><th>Type</th><th>Total</th><th>Occupied</th><th>Rate</th></tr></thead>
                                <tbody>
                                    ${Utils.roomTypes.map(t => {
                                        const typeRooms = rooms.filter(r => r.roomType === t);
                                        const typeOccupied = typeRooms.filter(r => r.status === 'Occupied').length;
                                        return typeRooms.length > 0 ? `
                                        <tr>
                                            <td>${t}</td>
                                            <td>${typeRooms.length}</td>
                                            <td>${typeOccupied}</td>
                                            <td>${(typeOccupied / (typeRooms.length || 1) * 100).toFixed(1)}%</td>
                                        </tr>` : '';
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    ${audits.length > 0 ? `
                    <h6>Occupancy Trend Data</h6>
                    <table class="table table-sm data-table">
                        <thead><tr><th>Date</th><th>Occupancy Rate</th><th>Occupied</th><th>Total Rooms</th></tr></thead>
                        <tbody>
                            ${audits.map(a => `<tr><td>${Utils.formatDate(a.date)}</td><td><strong>${a.occupancyRate}%</strong></td><td>${a.occupiedRooms}</td><td>${a.totalRooms}</td></tr>`).join('')}
                        </tbody>
                    </table>` : '<p class="text-muted">Run Night Audit to track occupancy trends</p>'}
                </div>
            </div>
        `;
    },

    _renderOccupancyCharts() {
        const rooms = DB.getAll(DB.ROOMS);
        const statuses = {};
        rooms.forEach(r => { const s = r.status || 'Available'; statuses[s] = (statuses[s] || 0) + 1; });
        const statusColors = { 'Available': '#27ae60', 'Occupied': '#C4943A', 'Maintenance': '#8B5E3C', 'Cleaning': '#5C2E15', 'Reserved': '#D4AD5E' };

        // Doughnut — Room Status
        const statusLabels = Object.keys(statuses);
        const statusData = Object.values(statuses);
        const ctx1 = document.getElementById('chartOccStatus');
        if (ctx1) {
            this._charts['chartOccStatus'] = new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: statusLabels.map(s => statusColors[s] || this._softColors[statusLabels.indexOf(s) % this._softColors.length]),
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 8
                    }]
                },
                options: this._defaultOptions({
                    cutout: '60%',
                    plugins: {
                        ...this._defaultOptions().plugins,
                        legend: { ...this._defaultOptions().plugins.legend, position: 'bottom' }
                    }
                })
            });
        }

        // Bar — Occupancy by Room Type
        const typeLabels = []; const typeTotal = []; const typeOccupied = [];
        Utils.roomTypes.forEach(t => {
            const tr = rooms.filter(r => r.roomType === t);
            if (tr.length > 0) {
                typeLabels.push(t);
                typeTotal.push(tr.length);
                typeOccupied.push(tr.filter(r => r.status === 'Occupied').length);
            }
        });
        const ctx2 = document.getElementById('chartOccType');
        if (ctx2) {
            this._charts['chartOccType'] = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [
                        { label: 'Total Rooms', data: typeTotal, backgroundColor: this._alpha('#5C2E15', 0.75), borderColor: '#5C2E15', borderWidth: 1, borderRadius: 6, borderSkipped: false },
                        { label: 'Occupied', data: typeOccupied, backgroundColor: this._alpha('#C4943A', 0.8), borderColor: '#C4943A', borderWidth: 1, borderRadius: 6, borderSkipped: false }
                    ]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { ...this._defaultOptions().plugins.legend, position: 'top' } },
                    scales: this._scales({ ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } } }),
                    barPercentage: 0.7, categoryPercentage: 0.8
                })
            });
        }

        // Line — Occupancy Trend
        const audits = DB.getAll(DB.NIGHT_AUDITS).sort((a,b) => new Date(a.date) - new Date(b.date)).slice(-30);
        const ctx3 = document.getElementById('chartOccTrend');
        if (ctx3 && audits.length > 0) {
            this._charts['chartOccTrend'] = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: audits.map(a => Utils.formatDate(a.date)),
                    datasets: [{
                        label: 'Occupancy Rate (%)',
                        data: audits.map(a => parseFloat(a.occupancyRate) || 0),
                        borderColor: '#C4943A',
                        backgroundColor: this._gradient(ctx3.getContext('2d'), '#C4943A', '#D4AD5E'),
                        fill: true, tension: 0.4,
                        pointRadius: 5, pointBackgroundColor: '#fff', pointBorderColor: '#C4943A', pointBorderWidth: 2.5,
                        pointHoverRadius: 7, pointHoverBackgroundColor: '#C4943A', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3,
                        borderWidth: 3
                    }]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { display: false } },
                    scales: this._scales({ max: 100, ticks: { callback: v => v + '%', color: '#5C2E15', font: { size: 11 } } })
                })
            });
        }
    },

    /* ==========================================
       REVENUE REPORT
       ========================================== */
    revenueReport() {
        const bills = DB.getAll(DB.BILLS);
        const totalRevenue = bills.reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const totalRoomCharges = bills.reduce((sum, b) => sum + (parseFloat(b.roomCharges) || 0), 0);
        const totalServiceCharges = bills.reduce((sum, b) => sum + (parseFloat(b.serviceCharges) || 0) + (parseFloat(b.amenityCharges) || 0), 0);
        const totalTax = bills.reduce((sum, b) => sum + (parseFloat(b.tax) || 0), 0);
        const totalOutstanding = bills.filter(b => b.status === 'Unpaid').reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0);

        const dailyRevenue = {};
        bills.forEach(b => {
            const date = (b.createdAt || '').split('T')[0];
            if (!dailyRevenue[date]) dailyRevenue[date] = { total: 0, count: 0 };
            dailyRevenue[date].total += parseFloat(b.totalAmount) || 0;
            dailyRevenue[date].count++;
        });

        return `
            <div class="module-card report-section" id="rpt-revenue">
                <div class="card-header">
                    <span><i class="bi bi-currency-rupee me-2"></i>Revenue Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-revenue')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-revenue', 'Revenue_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><div class="stat-card"><div class="stat-value">${Utils.formatCurrency(totalRevenue)}</div><div class="stat-label">Total Revenue</div></div></div>
                        <div class="col-md-3"><div class="stat-card" style="border-left-color:#5C2E15"><div class="stat-value">${Utils.formatCurrency(totalRoomCharges)}</div><div class="stat-label">Room Charges</div></div></div>
                        <div class="col-md-3"><div class="stat-card" style="border-left-color:#8B5E3C"><div class="stat-value">${Utils.formatCurrency(totalServiceCharges)}</div><div class="stat-label">Service Charges</div></div></div>
                        <div class="col-md-3"><div class="stat-card" style="border-left-color:#3B1A0A"><div class="stat-value">${Utils.formatCurrency(totalOutstanding)}</div><div class="stat-label">Outstanding</div></div></div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-5">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>Revenue Breakdown</h6>
                                <canvas id="chartRevBreakdown"></canvas>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Daily Revenue</h6>
                                <canvas id="chartRevDaily"></canvas>
                            </div>
                        </div>
                    </div>

                    <h6>Revenue Breakdown</h6>
                    <table class="table data-table">
                        <thead><tr><th>Category</th><th>Amount</th><th>% of Total</th></tr></thead>
                        <tbody>
                            <tr><td>Room Charges</td><td>${Utils.formatCurrency(totalRoomCharges)}</td><td>${totalRevenue > 0 ? (totalRoomCharges/totalRevenue*100).toFixed(1) : 0}%</td></tr>
                            <tr><td>Service & Amenity Charges</td><td>${Utils.formatCurrency(totalServiceCharges)}</td><td>${totalRevenue > 0 ? (totalServiceCharges/totalRevenue*100).toFixed(1) : 0}%</td></tr>
                            <tr><td>Tax (GST)</td><td>${Utils.formatCurrency(totalTax)}</td><td>${totalRevenue > 0 ? (totalTax/totalRevenue*100).toFixed(1) : 0}%</td></tr>
                        </tbody>
                    </table>
                    <h6 class="mt-4">Daily Revenue</h6>
                    <table class="table table-sm data-table">
                        <thead><tr><th>Date</th><th>Bills</th><th>Revenue</th></tr></thead>
                        <tbody>
                            ${Object.entries(dailyRevenue).sort((a,b) => new Date(b[0]) - new Date(a[0])).map(([date, data]) =>
                                `<tr><td>${Utils.formatDate(date)}</td><td>${data.count}</td><td><strong>${Utils.formatCurrency(data.total)}</strong></td></tr>`
                            ).join('') || '<tr><td colspan="3" class="text-center text-muted">No revenue data</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    _renderRevenueCharts() {
        const bills = DB.getAll(DB.BILLS);
        const totalRoomCharges = bills.reduce((sum, b) => sum + (parseFloat(b.roomCharges) || 0), 0);
        const totalServiceCharges = bills.reduce((sum, b) => sum + (parseFloat(b.serviceCharges) || 0) + (parseFloat(b.amenityCharges) || 0), 0);
        const totalTax = bills.reduce((sum, b) => sum + (parseFloat(b.tax) || 0), 0);

        // Pie — Revenue Breakdown
        const ctx1 = document.getElementById('chartRevBreakdown');
        if (ctx1) {
            this._charts['chartRevBreakdown'] = new Chart(ctx1, {
                type: 'pie',
                data: {
                    labels: ['Room Charges', 'Service & Amenity', 'Tax (GST)'],
                    datasets: [{
                        data: [totalRoomCharges, totalServiceCharges, totalTax],
                        backgroundColor: ['#3B1A0A','#C4943A','#8B5E3C'],
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 10
                    }]
                },
                options: this._defaultOptions()
            });
        }

        // Bar — Daily Revenue
        const dailyRevenue = {};
        bills.forEach(b => {
            const date = (b.createdAt || '').split('T')[0];
            if (date) {
                if (!dailyRevenue[date]) dailyRevenue[date] = 0;
                dailyRevenue[date] += parseFloat(b.totalAmount) || 0;
            }
        });
        const sorted = Object.entries(dailyRevenue).sort((a,b) => new Date(a[0]) - new Date(b[0])).slice(-30);
        const ctx2 = document.getElementById('chartRevDaily');
        if (ctx2 && sorted.length > 0) {
            this._charts['chartRevDaily'] = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: sorted.map(([d]) => Utils.formatDate(d)),
                    datasets: [{
                        label: 'Revenue',
                        data: sorted.map(([,v]) => v),
                        backgroundColor: this._alpha('#C4943A', 0.8),
                        borderColor: '#C4943A', borderWidth: 1,
                        borderRadius: 8, borderSkipped: false,
                        hoverBackgroundColor: '#3B1A0A'
                    }]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { display: false } },
                    scales: this._scales(),
                    barPercentage: 0.7
                })
            });
        }
    },

    /* ==========================================
       GUEST REPORT
       ========================================== */
    guestReport() {
        const guests = DB.getAll(DB.GUESTS);
        const bookings = DB.getAll(DB.BOOKINGS);
        const guestData = guests.map(g => {
            const gBookings = bookings.filter(b => b.mobile === g.mobile);
            return { ...g, bookingCount: gBookings.length };
        }).sort((a, b) => b.bookingCount - a.bookingCount);

        const nationalities = {};
        guests.forEach(g => { const nat = g.nationality || 'Indian'; nationalities[nat] = (nationalities[nat] || 0) + 1; });

        const repeatCount = guestData.filter(g => g.bookingCount > 1).length;
        const newCount = guestData.filter(g => g.bookingCount <= 1).length;

        return `
            <div class="module-card report-section" id="rpt-guest">
                <div class="card-header">
                    <span><i class="bi bi-people me-2"></i>Guest Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-guest')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-guest', 'Guest_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="stat-card"><div class="stat-value">${guests.length}</div><div class="stat-label">Total Guests</div></div></div>
                        <div class="col-md-4"><div class="stat-card" style="border-left-color:#5C2E15"><div class="stat-value">${repeatCount}</div><div class="stat-label">Repeat Guests</div></div></div>
                        <div class="col-md-4"><div class="stat-card" style="border-left-color:#8B5E3C"><div class="stat-value">${Object.keys(nationalities).length}</div><div class="stat-label">Nationalities</div></div></div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>Guest Nationality Distribution</h6>
                                <canvas id="chartGuestNat"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>New vs Repeat Guests</h6>
                                <canvas id="chartGuestRepeat"></canvas>
                            </div>
                        </div>
                    </div> 
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Top Guests by Stays</h6>
                                <canvas id="chartGuestTop" style="max-height:300px"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-8">
                            <h6>Guest Directory</h6>
                            <table class="table table-sm data-table">
                                <thead><tr><th>Name</th><th>Mobile</th><th>Nationality</th><th>Stays</th></tr></thead>
                                <tbody>
                                    ${guestData.slice(0, 50).map(g => `
                                        <tr>
                                            <td>${Utils.escapeHtml(g.name)}</td>
                                            <td>${Utils.escapeHtml(g.mobile || '-')}</td>
                                            <td>${Utils.escapeHtml(g.nationality || 'Indian')}</td>
                                            <td><span class="badge bg-primary">${g.bookingCount}</span></td>
                                        </tr>
                                    `).join('') || '<tr><td colspan="4" class="text-center text-muted">No guests</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h6>By Nationality</h6>
                            <table class="table table-sm">
                                <tbody>
                                    ${Object.entries(nationalities).sort((a,b) => b[1] - a[1]).map(([nat, count]) =>
                                        `<tr><td>${Utils.escapeHtml(nat)}</td><td><strong>${count}</strong></td></tr>`
                                    ).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    _renderGuestCharts() {
        const guests = DB.getAll(DB.GUESTS);
        const bookings = DB.getAll(DB.BOOKINGS);
        const guestData = guests.map(g => {
            const gBookings = bookings.filter(b => b.mobile === g.mobile);
            return { ...g, bookingCount: gBookings.length };
        }).sort((a, b) => b.bookingCount - a.bookingCount);

        const nationalities = {};
        guests.forEach(g => { const nat = g.nationality || 'Indian'; nationalities[nat] = (nationalities[nat] || 0) + 1; });

        // Doughnut — Nationality
        const natEntries = Object.entries(nationalities).sort((a,b) => b[1] - a[1]);
        const ctx1 = document.getElementById('chartGuestNat');
        if (ctx1 && natEntries.length > 0) {
            this._charts['chartGuestNat'] = new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: natEntries.map(([n]) => n),
                    datasets: [{
                        data: natEntries.map(([,c]) => c),
                        backgroundColor: natEntries.map((_,i) => this._softColors[i % this._softColors.length]),
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 8
                    }]
                },
                options: this._defaultOptions({ cutout: '55%' })
            });
        }

        // Pie — Repeat vs New
        const repeatCount = guestData.filter(g => g.bookingCount > 1).length;
        const newCount = guestData.filter(g => g.bookingCount <= 1).length;
        const ctx2 = document.getElementById('chartGuestRepeat');
        if (ctx2 && guests.length > 0) {
            this._charts['chartGuestRepeat'] = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['New Guests', 'Repeat Guests'],
                    datasets: [{
                        data: [newCount, repeatCount],
                        backgroundColor: ['#5C2E15','#C4943A'],
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 8
                    }]
                },
                options: this._defaultOptions({ cutout: '55%' })
            });
        }

        // Horizontal Bar — Top 10 Guests by stays
        const top = guestData.filter(g => g.bookingCount > 0).slice(0, 10);
        const ctx3 = document.getElementById('chartGuestTop');
        if (ctx3 && top.length > 0) {
            this._charts['chartGuestTop'] = new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: top.map(g => g.name),
                    datasets: [{
                        label: 'Stays',
                        data: top.map(g => g.bookingCount),
                        backgroundColor: top.map((_,i) => this._alpha(this._softColors[i % this._softColors.length], 0.8)),
                        borderColor: top.map((_,i) => this._softColors[i % this._softColors.length]),
                        borderWidth: 1, borderRadius: 8, borderSkipped: false,
                        barThickness: 24
                    }]
                },
                options: this._defaultOptions({
                    indexAxis: 'y',
                    plugins: { ...this._defaultOptions().plugins, legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } }, grid: { color: 'rgba(59,26,10,0.06)', drawBorder: false }, border: { display: false } },
                        y: { ticks: { font: { size: 12, weight: '500' }, padding: 10, color: '#3B1A0A' }, grid: { display: false }, border: { display: false } }
                    },
                    layout: { padding: { left: 10, right: 24 } }
                })
            });
        }
    },

    /* ==========================================
       AVAILABILITY REPORT
       ========================================== */
    availabilityReport() {
        const rooms = DB.getAll(DB.ROOMS);
        const bookings = DB.getAll(DB.BOOKINGS).filter(b => b.status !== 'Cancelled');

        return `
            <div class="module-card report-section" id="rpt-availability">
                <div class="card-header">
                    <span><i class="bi bi-door-open me-2"></i>Room Availability Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-availability')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-availability', 'Room_Availability_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Chart -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Availability by Room Type</h6>
                                <canvas id="chartAvailType"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Rooms by Floor</h6>
                                <canvas id="chartAvailFloor"></canvas>
                            </div>
                        </div>
                    </div>

                    <table class="table data-table">
                        <thead>
                            <tr><th>Room No</th><th>Type</th><th>Floor</th><th>Price</th><th>Current Status</th><th>Current Guest</th><th>Upcoming Bookings</th></tr>
                        </thead>
                        <tbody>
                            ${rooms.map(r => {
                                const activeCheckin = DB.query(DB.CHECKINS, c => c.roomId === r.id && c.status === 'Checked-In')[0];
                                const futureBookings = bookings.filter(b => b.roomId === r.id && b.checkIn >= Utils.today());
                                return `
                                <tr>
                                    <td><strong>${Utils.escapeHtml(r.roomNumber)}</strong></td>
                                    <td>${Utils.escapeHtml(r.roomType)}</td>
                                    <td>${Utils.escapeHtml(r.floor || '-')}</td>
                                    <td>${Utils.formatCurrency(r.price)}</td>
                                    <td>${Utils.statusBadge(r.status || 'Available')}</td>
                                    <td>${activeCheckin ? Utils.escapeHtml(activeCheckin.guestName) : '-'}</td>
                                    <td>${futureBookings.length > 0 ? futureBookings.map(b => `<small>${Utils.formatDate(b.checkIn)}</small>`).join(', ') : '<small class="text-muted">None</small>'}</td>
                                </tr>`;
                            }).join('') || '<tr><td colspan="7" class="text-center text-muted">No rooms</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    _renderAvailabilityCharts() {
        const rooms = DB.getAll(DB.ROOMS);

        // Stacked bar by Room Type: Available vs Occupied vs Other
        const typeMap = {};
        rooms.forEach(r => {
            const t = r.roomType || 'Other';
            if (!typeMap[t]) typeMap[t] = { Available: 0, Occupied: 0, Other: 0 };
            const s = r.status || 'Available';
            if (s === 'Available') typeMap[t].Available++;
            else if (s === 'Occupied') typeMap[t].Occupied++;
            else typeMap[t].Other++;
        });
        const typeLabels = Object.keys(typeMap);
        const ctx1 = document.getElementById('chartAvailType');
        if (ctx1 && typeLabels.length > 0) {
            this._charts['chartAvailType'] = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [
                        { label: 'Available', data: typeLabels.map(t => typeMap[t].Available), backgroundColor: '#27ae60', borderRadius: 6, borderSkipped: false },
                        { label: 'Occupied', data: typeLabels.map(t => typeMap[t].Occupied), backgroundColor: '#C4943A', borderRadius: 6, borderSkipped: false },
                        { label: 'Other', data: typeLabels.map(t => typeMap[t].Other), backgroundColor: '#8B5E3C', borderRadius: 6, borderSkipped: false }
                    ]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { ...this._defaultOptions().plugins.legend, position: 'top' } },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { color: '#5C2E15', font: { size: 11 } }, border: { display: false } },
                        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } }, grid: { color: 'rgba(59,26,10,0.06)', drawBorder: false }, border: { display: false } }
                    }
                })
            });
        }

        // Bar by Floor
        const floorMap = {};
        rooms.forEach(r => {
            const f = r.floor || 'N/A';
            if (!floorMap[f]) floorMap[f] = { Available: 0, Occupied: 0 };
            const s = r.status || 'Available';
            if (s === 'Available') floorMap[f].Available++; else floorMap[f].Occupied++;
        });
        const floorLabels = Object.keys(floorMap).sort();
        const ctx2 = document.getElementById('chartAvailFloor');
        if (ctx2 && floorLabels.length > 0) {
            this._charts['chartAvailFloor'] = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: floorLabels.map(f => 'Floor ' + f),
                    datasets: [
                        { label: 'Available', data: floorLabels.map(f => floorMap[f].Available), backgroundColor: this._alpha('#27ae60', 0.8), borderColor: '#27ae60', borderWidth: 1, borderRadius: 6, borderSkipped: false },
                        { label: 'Occupied', data: floorLabels.map(f => floorMap[f].Occupied), backgroundColor: this._alpha('#C4943A', 0.8), borderColor: '#C4943A', borderWidth: 1, borderRadius: 6, borderSkipped: false }
                    ]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { ...this._defaultOptions().plugins.legend, position: 'top' } },
                    scales: this._scales({ ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } } }),
                    barPercentage: 0.7
                })
            });
        }
    },

    /* ==========================================
       CHECK-IN / CHECK-OUT REPORT
       ========================================== */
    checkInOutReport() {
        const checkins = DB.getAll(DB.CHECKINS).sort((a,b) => new Date(b.checkInDate) - new Date(a.checkInDate));

        return `
            <div class="module-card report-section" id="rpt-checkinout">
                <div class="card-header">
                    <span><i class="bi bi-arrow-left-right me-2"></i>Check-In / Check-Out Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-checkinout')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-checkinout', 'CheckInOut_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Charts -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-7">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Check-Ins & Check-Outs per Day</h6>
                                <canvas id="chartCIODaily"></canvas>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>Status Distribution</h6>
                                <canvas id="chartCIOStatus"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table data-table">
                            <thead>
                                <tr><th>Guest</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Duration</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                ${checkins.length === 0 ? '<tr><td colspan="6" class="text-center py-4 text-muted">No check-in records</td></tr>' :
                                checkins.map(c => {
                                    const room = DB.getById(DB.ROOMS, c.roomId);
                                    const days = c.checkOutDate ? Utils.daysBetween(c.checkInDate, c.checkOutDate) : Utils.daysBetween(c.checkInDate, Utils.today());
                                    return `
                                    <tr>
                                        <td><strong>${Utils.escapeHtml(c.guestName)}</strong><br><small>${Utils.escapeHtml(c.mobile)}</small></td>
                                        <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                        <td>${Utils.formatDate(c.checkInDate)}</td>
                                        <td>${c.checkOutDate ? Utils.formatDate(c.checkOutDate) : '<span class="badge bg-success">Still here</span>'}</td>
                                        <td>${days} night(s)</td>
                                        <td>${Utils.statusBadge(c.status)}</td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    },

    _renderCheckinCharts() {
        const checkins = DB.getAll(DB.CHECKINS);

        // Bar — Check-ins/Check-outs per day
        const dailyCI = {}; const dailyCO = {};
        checkins.forEach(c => {
            const ciDate = (c.checkInDate || '').split('T')[0];
            if (ciDate) dailyCI[ciDate] = (dailyCI[ciDate] || 0) + 1;
            if (c.checkOutDate) {
                const coDate = (c.checkOutDate || '').split('T')[0];
                if (coDate) dailyCO[coDate] = (dailyCO[coDate] || 0) + 1;
            }
        });
        const allDates = [...new Set([...Object.keys(dailyCI), ...Object.keys(dailyCO)])].sort().slice(-30);
        const ctx1 = document.getElementById('chartCIODaily');
        if (ctx1 && allDates.length > 0) {
            this._charts['chartCIODaily'] = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: allDates.map(d => Utils.formatDate(d)),
                    datasets: [
                        { label: 'Check-Ins', data: allDates.map(d => dailyCI[d] || 0), backgroundColor: this._alpha('#C4943A', 0.8), borderColor: '#C4943A', borderWidth: 1, borderRadius: 6, borderSkipped: false },
                        { label: 'Check-Outs', data: allDates.map(d => dailyCO[d] || 0), backgroundColor: this._alpha('#5C2E15', 0.75), borderColor: '#5C2E15', borderWidth: 1, borderRadius: 6, borderSkipped: false }
                    ]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { ...this._defaultOptions().plugins.legend, position: 'top' } },
                    scales: this._scales({ ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } } }),
                    barPercentage: 0.7
                })
            });
        }

        // Doughnut — Status
        const statusMap = {};
        checkins.forEach(c => { const s = c.status || 'Unknown'; statusMap[s] = (statusMap[s] || 0) + 1; });
        const statusLabels = Object.keys(statusMap);
        const statusColors = { 'Checked-In': '#27ae60', 'Checked-Out': '#C4943A', 'Cancelled': '#3B1A0A' };
        const ctx2 = document.getElementById('chartCIOStatus');
        if (ctx2 && statusLabels.length > 0) {
            this._charts['chartCIOStatus'] = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusLabels.map(s => statusMap[s]),
                        backgroundColor: statusLabels.map((s,i) => statusColors[s] || this._softColors[i % this._softColors.length]),
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 8
                    }]
                },
                options: this._defaultOptions({ cutout: '55%' })
            });
        }
    },

    /* ==========================================
       SERVICES REPORT
       ========================================== */
    servicesReport() {
        const guestServices = DB.getAll(DB.GUEST_SERVICES);
        const roomServiceOrders = DB.getAll(DB.ROOM_SERVICE);
        const totalServiceRevenue = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);
        const totalRoomServiceRevenue = roomServiceOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);

        const serviceBreakdown = {};
        guestServices.forEach(gs => {
            const name = gs.serviceName || 'Unknown';
            if (!serviceBreakdown[name]) serviceBreakdown[name] = { count: 0, total: 0 };
            serviceBreakdown[name].count++;
            serviceBreakdown[name].total += parseFloat(gs.charge) || 0;
        });

        return `
            <div class="module-card report-section" id="rpt-services">
                <div class="card-header">
                    <span><i class="bi bi-stars me-2"></i>Services Report</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" onclick="ReportsModule.printSection('rpt-services')"><i class="bi bi-printer me-1"></i>Print</button>
                        <button class="btn btn-sm btn-accent" onclick="ReportsModule.downloadPDF('rpt-services', 'Services_Report')"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="stat-card"><div class="stat-value">${Utils.formatCurrency(totalRoomServiceRevenue)}</div><div class="stat-label">Room Service Revenue</div></div></div>
                        <div class="col-md-4"><div class="stat-card" style="border-left-color:#5C2E15"><div class="stat-value">${Utils.formatCurrency(totalServiceRevenue)}</div><div class="stat-label">Amenity Revenue</div></div></div>
                        <div class="col-md-4"><div class="stat-card" style="border-left-color:#8B5E3C"><div class="stat-value">${Utils.formatCurrency(totalRoomServiceRevenue + totalServiceRevenue)}</div><div class="stat-label">Combined</div></div></div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-5">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-pie-chart me-1"></i>Room Service vs Amenity Revenue</h6>
                                <canvas id="chartSvcSplit"></canvas>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="chart-container">
                                <h6 class="chart-title"><i class="bi bi-bar-chart me-1"></i>Service Usage & Revenue</h6>
                                <canvas id="chartSvcBreakdown"></canvas>
                            </div>
                        </div>
                    </div>

                    <h6>Amenity Service Breakdown</h6>
                    <table class="table data-table">
                        <thead><tr><th>Service</th><th>Times Used</th><th>Revenue</th></tr></thead>
                        <tbody>
                            ${Object.entries(serviceBreakdown).sort((a,b) => b[1].total - a[1].total).map(([name, data]) =>
                                `<tr><td>${Utils.escapeHtml(name)}</td><td>${data.count}</td><td>${Utils.formatCurrency(data.total)}</td></tr>`
                            ).join('') || '<tr><td colspan="3" class="text-center text-muted">No service data</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    _renderServicesCharts() {
        const guestServices = DB.getAll(DB.GUEST_SERVICES);
        const roomServiceOrders = DB.getAll(DB.ROOM_SERVICE);
        const totalServiceRevenue = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);
        const totalRoomServiceRevenue = roomServiceOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);

        // Doughnut — Room Service vs Amenity
        const ctx1 = document.getElementById('chartSvcSplit');
        if (ctx1 && (totalRoomServiceRevenue > 0 || totalServiceRevenue > 0)) {
            this._charts['chartSvcSplit'] = new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Room Service', 'Amenity Services'],
                    datasets: [{
                        data: [totalRoomServiceRevenue, totalServiceRevenue],
                        backgroundColor: ['#3B1A0A','#C4943A'],
                        borderWidth: 3, borderColor: '#fff',
                        hoverBorderWidth: 4, hoverOffset: 8
                    }]
                },
                options: this._defaultOptions({ cutout: '55%' })
            });
        }

        // Bar — Individual Service Breakdown
        const serviceBreakdown = {};
        guestServices.forEach(gs => {
            const name = gs.serviceName || 'Unknown';
            if (!serviceBreakdown[name]) serviceBreakdown[name] = { count: 0, total: 0 };
            serviceBreakdown[name].count++;
            serviceBreakdown[name].total += parseFloat(gs.charge) || 0;
        });
        const svcEntries = Object.entries(serviceBreakdown).sort((a,b) => b[1].total - a[1].total).slice(0, 10);
        const ctx2 = document.getElementById('chartSvcBreakdown');
        if (ctx2 && svcEntries.length > 0) {
            this._charts['chartSvcBreakdown'] = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: svcEntries.map(([n]) => n),
                    datasets: [
                        { label: 'Revenue', data: svcEntries.map(([,d]) => d.total), backgroundColor: this._alpha('#C4943A', 0.8), borderColor: '#C4943A', borderWidth: 1, borderRadius: 6, borderSkipped: false, yAxisID: 'y' },
                        { label: 'Usage Count', data: svcEntries.map(([,d]) => d.count), backgroundColor: this._alpha('#5C2E15', 0.75), borderColor: '#5C2E15', borderWidth: 1, borderRadius: 6, borderSkipped: false, yAxisID: 'y1' }
                    ]
                },
                options: this._defaultOptions({
                    plugins: { ...this._defaultOptions().plugins, legend: { ...this._defaultOptions().plugins.legend, position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Revenue (₹)', color: '#5C2E15', font: { size: 12, weight: '600' } }, grid: { color: 'rgba(59,26,10,0.06)', drawBorder: false }, ticks: { color: '#5C2E15', font: { size: 11 } }, border: { display: false } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Count', color: '#5C2E15', font: { size: 12, weight: '600' } }, ticks: { stepSize: 1, color: '#5C2E15', font: { size: 11 } }, border: { display: false } },
                        x: { grid: { display: false }, ticks: { color: '#5C2E15', font: { size: 11 } }, border: { display: false } }
                    },
                    barPercentage: 0.7
                })
            });
        }
    },

    // ── Print a specific report section only ──────────────────────
    printSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) { window.print(); return; }
        section.classList.add('printing');
        const origTitle = document.title;
        document.title = section.querySelector('.card-header span')?.textContent?.trim() || 'Report';
        window.print();
        setTimeout(() => {
            section.classList.remove('printing');
            document.title = origTitle;
        }, 1000);
    },

    // ── Download report section as PDF using jsPDF + html2canvas ──
    async downloadPDF(sectionId, filename) {
        const section = document.getElementById(sectionId);
        if (!section) { Utils.showToast('Section not found', 'danger'); return; }

        if (!window.jspdf) {
            await new Promise((res, rej) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                s.onload = res; s.onerror = rej; document.head.appendChild(s);
            });
        }
        if (!window.html2canvas) {
            await new Promise((res, rej) => {
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                s.onload = res; s.onerror = rej; document.head.appendChild(s);
            });
        }

        Utils.showToast('Generating PDF, please wait...', 'info');

        try {
            const canvas = await html2canvas(section, {
                scale: 1.5, useCORS: true, backgroundColor: '#ffffff', logging: false,
                onclone: (doc) => {
                    const btns = doc.getElementById(sectionId)?.querySelector('.card-header .d-flex');
                    if (btns) btns.style.display = 'none';
                }
            });

            const { jsPDF } = window.jspdf;
            const pageW = 210, margin = 12, contentW = pageW - margin * 2;
            const imgH  = (canvas.height * contentW) / canvas.width;
            const pageH = 297;
            const doc   = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            const today = new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });

            // Header
            doc.setFillColor(59, 26, 10);
            doc.rect(0, 0, pageW, 22, 'F');
            doc.setFont('times', 'bolditalic'); doc.setFontSize(16);
            doc.setTextColor(196, 148, 58);
            doc.text('Karavali Lodge', pageW / 2, 11, { align: 'center' });
            doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5);
            doc.setTextColor(200, 180, 140);
            doc.text('K.S. Rao Road, Mangalore - 575001  |  0824-2441104', pageW / 2, 18, { align: 'center' });

            // Title bar
            const title = section.querySelector('.card-header span')?.textContent?.trim() || filename.replace(/_/g,' ');
            doc.setFillColor(245, 240, 232);
            doc.rect(0, 22, pageW, 9, 'F');
            doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(59, 26, 10);
            doc.text(title, margin, 28);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(139, 115, 85);
            doc.text('Generated: ' + today, pageW - margin, 28, { align: 'right' });

            // Paginated content image
            let yPos = 34, remaining = imgH, srcY = 0, pageNum = 0;
            while (remaining > 0) {
                if (pageNum > 0) { doc.addPage(); yPos = margin; }
                const sliceH     = Math.min(remaining, pageH - yPos - 14);
                const srcH       = (sliceH * canvas.width) / contentW;
                const sliceCanvas = document.createElement('canvas');
                sliceCanvas.width = canvas.width; sliceCanvas.height = srcH;
                sliceCanvas.getContext('2d').drawImage(canvas, 0, srcY, canvas.width, srcH, 0, 0, canvas.width, srcH);
                doc.addImage(sliceCanvas.toDataURL('image/png'), 'PNG', margin, yPos, contentW, sliceH);
                srcY += srcH; remaining -= sliceH; yPos = margin; pageNum++;
            }

            // Footer on every page
            const totalPages = doc.getNumberOfPages();
            for (let p = 1; p <= totalPages; p++) {
                doc.setPage(p);
                doc.setFillColor(59, 26, 10); doc.rect(0, 289, pageW, 8, 'F');
                doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(196, 148, 58);
                doc.text('Karavali Lodge  |  Confidential Report', margin, 294);
                doc.text(`Page ${p} of ${totalPages}`, pageW - margin, 294, { align: 'right' });
            }

            doc.save(`${filename}_${today.replace(/ /g,'-')}.pdf`);
            Utils.showToast('Report downloaded as PDF', 'success');

        } catch(e) {
            console.error('PDF error:', e);
            Utils.showToast('PDF generation failed. Try Print instead.', 'danger');
        }
    }

};
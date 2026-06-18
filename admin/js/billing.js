/* ==========================================
   Room Billing Module
   ========================================== */
const BillingModule = {
    render() {
        const bills = DB.getAll(DB.BILLS);
        const totalRevenue = bills.reduce((sum, b) => sum + (parseFloat(b.totalAmount) || 0), 0);
        const unpaidBills = bills.filter(b => b.status === 'Unpaid');

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bills.length}</div>
                                <div class="stat-label">Total Bills</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${Utils.formatCurrency(totalRevenue)}</div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-currency-rupee"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${unpaidBills.length}</div>
                                <div class="stat-label">Unpaid Bills</div>
                            </div>
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${Utils.formatCurrency(unpaidBills.reduce((sum, b) => sum + (parseFloat(b.balance) || 0), 0))}</div>
                                <div class="stat-label">Outstanding</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-wallet2"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-bar">
                <input type="text" class="form-control" id="billSearch" placeholder="Search bills..." oninput="BillingModule.filterBills()">
                <select class="form-select" id="billStatusFilter" onchange="BillingModule.filterBills()">
                    <option value="">All Status</option>
                    <option value="Paid">Paid</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>

            <div class="module-card">
                <div class="card-header">
                    <span><i class="bi bi-receipt me-2"></i>Bills & Invoices</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table data-table" id="billTable">
                            <thead>
                                <tr>
                                    <th>Bill No</th>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Nights</th>
                                    <th>Room Charges</th>
                                    <th>Services</th>
                                    <th>Tax</th>
                                    <th>Total</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${bills.length === 0 ? '<tr><td colspan="13" class="text-center py-4 text-muted">No bills generated yet</td></tr>' :
                                bills.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).map(b => {
                                    const room = DB.getById(DB.ROOMS, b.roomId);
                                    return `
                                    <tr>
                                        <td><strong>${Utils.escapeHtml(b.billNo)}</strong></td>
                                        <td>${Utils.escapeHtml(b.guestName)}</td>
                                        <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                        <td>${Utils.formatDate(b.checkIn)}${(() => {
                                            const ci = DB.query(DB.CHECKINS, c => c.id === b.checkinId)[0]
                                                    || DB.query(DB.CHECKINS, c => c.bookingId === b.id)[0];
                                            if (ci && ci.checkInTime) {
                                                const t = new Date(ci.checkInTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
                                                return `<br><small class="text-muted"><i class="bi bi-clock me-1"></i>${t}</small>`;
                                            }
                                            return '';
                                        })()}</td>
                                        <td>${Utils.formatDate(b.checkOut)}${(() => {
                                            const ci = DB.query(DB.CHECKINS, c => c.id === b.checkinId)[0];
                                            if (ci && ci.checkOutTime) {
                                                const t = new Date(ci.checkOutTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
                                                return `<br><small class="text-muted"><i class="bi bi-clock me-1"></i>${t}</small>`;
                                            }
                                            return '';
                                        })()}</td>
                                        <td>${b.nights || '-'}</td>
                                        <td>
                                            ${Utils.formatCurrency(b.roomCharges)}
                                            ${(parseFloat(b.extraCharge)||0) > 0 ? `<br><small style="color:#e67e22">+${Utils.formatCurrency(b.extraCharge)} extra</small>` : ''}
                                        </td>
                                        <td>${Utils.formatCurrency((parseFloat(b.serviceCharges)||0) + (parseFloat(b.amenityCharges)||0))}</td>
                                        <td>${Utils.formatCurrency(b.tax)}</td>
                                        <td><strong>${Utils.formatCurrency(b.totalAmount)}</strong></td>
                                        <td class="${(b.balance||0) > 0 ? 'text-danger' : 'text-success'}">${Utils.formatCurrency(b.balance)}</td>
                                        <td>${Utils.statusBadge(b.status)}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="BillingModule.viewBill('${b.id}')" title="View"><i class="bi bi-eye"></i></button>
                                                ${b.status === 'Unpaid' ? `<button class="btn btn-outline-success" onclick="BillingModule.markPaid('${b.id}')" title="Mark Paid"><i class="bi bi-check"></i></button>` : ''}
                                                <button class="btn btn-outline-danger" onclick="BillingModule.downloadPDF('${b.id}')" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                                                <button class="btn btn-outline-secondary" onclick="BillingModule.printBill('${b.id}')" title="Print"><i class="bi bi-printer"></i></button>
                                            </div>
                                        </td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bill View Modal -->
            <div class="modal fade" id="billViewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Invoice Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="billViewContent">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-danger" onclick="BillingModule.downloadPDFFromModal()"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
                            <button type="button" class="btn btn-primary" onclick="BillingModule.triggerPrintFromModal()"><i class="bi bi-printer me-1"></i>Print</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    viewBill(id) {
        const bill = DB.getById(DB.BILLS, id);
        if (!bill) return;
        this._currentBillId = id;   // ← track for modal download button
        const room    = DB.getById(DB.ROOMS, bill.roomId);
        const checkin = DB.query(DB.CHECKINS, c => c.id === bill.checkinId)[0];

        // Format times if available
        const ciTime = checkin && checkin.checkInTime
            ? new Date(checkin.checkInTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})
            : '';
        const coTime = checkin && checkin.checkOutTime
            ? new Date(checkin.checkOutTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})
            : '';

        const html = `
            <div class="bill-container" id="printableBill">
                <div class="bill-header">
                    <h3>Karavali Lodge</h3>
                    <p class="text-muted mb-1">Tax Invoice</p>
                    <p class="mb-0"><strong>Bill No:</strong> ${Utils.escapeHtml(bill.billNo)} | <strong>Date:</strong> ${Utils.formatDate(bill.createdAt)}</p>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong>Guest:</strong> ${Utils.escapeHtml(bill.guestName)}<br>
                        <strong>Mobile:</strong> ${Utils.escapeHtml(bill.mobile || '-')}<br>
                        <strong>Room:</strong> ${room ? Utils.escapeHtml(room.roomNumber) + ' (' + Utils.escapeHtml(room.roomType) + ')' : '-'}
                    </div>
                    <div class="col-6 text-end">
                        <strong>Check-In:</strong> ${Utils.formatDate(bill.checkIn)} ${ciTime ? `<span style="color:#888;font-size:0.85em">${ciTime}</span>` : ''}<br>
                        <strong>Check-Out:</strong> ${Utils.formatDate(bill.checkOut)} ${coTime ? `<span style="color:#888;font-size:0.85em">${coTime}</span>` : ''}<br>
                        <strong>Duration:</strong> ${bill.nights} night(s)
                    </div>
                </div>
                <table class="table">
                    <thead class="table-light">
                        <tr><th>Description</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Room Charges (${bill.nights} night${bill.nights > 1 ? 's' : ''} × ${Utils.formatCurrency(room ? room.price : 0)})</td><td class="text-end">${Utils.formatCurrency(bill.roomCharges)}</td></tr>
                        ${(parseFloat(bill.extraCharge) || 0) > 0 ? `
                        <tr style="background:#fff9ec">
                            <td>
                                <span style="color:#e67e22;font-weight:600">⚡ Extra Time Charge</span>
                                ${bill.extraNote ? `<br><small style="color:#aaa">${Utils.escapeHtml(bill.extraNote)}</small>` : ''}
                            </td>
                            <td class="text-end" style="color:#e67e22;font-weight:600">${Utils.formatCurrency(bill.extraCharge)}</td>
                        </tr>` : ''}
                        <tr><td>Restaurant / Room Service Charges</td><td class="text-end">${Utils.formatCurrency(bill.serviceCharges || 0)}</td></tr>
                        <tr><td>Amenity / Extra Service Charges</td><td class="text-end">${Utils.formatCurrency(bill.amenityCharges || 0)}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td><strong>Subtotal</strong></td><td class="text-end"><strong>${Utils.formatCurrency((parseFloat(bill.roomCharges)||0) + (parseFloat(bill.extraCharge)||0) + (parseFloat(bill.serviceCharges)||0) + (parseFloat(bill.amenityCharges)||0))}</strong></td></tr>
                        <tr><td>GST (12%)</td><td class="text-end">${Utils.formatCurrency(bill.tax)}</td></tr>
                        <tr class="table-dark"><td><strong>Grand Total</strong></td><td class="text-end"><strong>${Utils.formatCurrency(bill.totalAmount)}</strong></td></tr>
                        <tr><td>Advance Paid</td><td class="text-end text-success">- ${Utils.formatCurrency(bill.advance || 0)}</td></tr>
                        <tr><td><strong>Balance Due</strong></td><td class="text-end text-danger"><strong>${Utils.formatCurrency(bill.balance)}</strong></td></tr>
                    </tfoot>
                </table>
                <div style="background:#faf7f2;border:1px solid #e8d9c0;border-radius:10px;padding:12px 16px;margin-top:16px;font-size:0.8rem;color:#6b5c45;line-height:1.7">
                    <div style="font-weight:700;color:#3B1A0A;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                        <i class="bi bi-clock-history" style="color:#C4943A"></i> Flexible Check-Out Billing Policy
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;">
                        <span>✦ First 24 hrs</span><span>= 1 Night Charge</span>
                        <span>✦ Extra 1–6 hrs</span><span>= Hourly rate (price ÷ 24)</span>
                        <span>✦ Extra 6–12 hrs</span><span>= Half-day charge (price ÷ 2)</span>
                        <span>✦ Extra 12+ hrs</span><span>= Full extra day charge</span>
                    </div>
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e8d9c0;color:#888;font-size:0.75rem">
                        All charges subject to GST @ 12%. Extra time charges apply beyond the free checkout window.
                    </div>
                </div>
                <div class="text-center text-muted mt-3">
                    <small>Thank you for staying at Karavali Lodge!</small>
                </div>
            </div>
        `;

        document.getElementById('billViewContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('billViewModal')).show();
    },

    triggerPrintFromModal() {
        setTimeout(() => window.print(), 150);
    },

    // ── Current bill id being viewed in modal ─────────────────────
    _currentBillId: null,

    downloadPDFFromModal() {
        if (this._currentBillId) this.downloadPDF(this._currentBillId);
    },

    async downloadPDF(id) {
        const bill = DB.getById(DB.BILLS, id);
        if (!bill) return;

        // Load jsPDF if not already loaded
        if (!window.jspdf) {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        const { jsPDF } = window.jspdf;
        const doc        = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const room       = DB.getById(DB.ROOMS, bill.roomId);
        const checkin    = DB.query(DB.CHECKINS, c => c.id === bill.checkinId)[0];
        const pageW      = 210;
        const margin     = 15;
        const contentW   = pageW - margin * 2;

        // ── PDF-safe currency: use "Rs." instead of ₹ (avoids font encoding issues) ──
        const cur = (amount) => 'Rs.' + Number(amount || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });

        // ── Format time cleanly without @ symbol ──────────────────
        const fmtTime = (isoStr) => isoStr
            ? new Date(isoStr).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
            : '';

        const ciTime = fmtTime(checkin?.checkInTime);
        const coTime = fmtTime(checkin?.checkOutTime);

        // Build display strings: "08 May 2026, 09:31 AM" — no @ symbol
        const ciDisplay = `${Utils.formatDate(bill.checkIn)}${ciTime ? ', ' + ciTime : ''}`;
        const coDisplay = `${Utils.formatDate(bill.checkOut)}${coTime ? ', ' + coTime : ''}`;

        let y = 0;

        // ── Header band ──────────────────────────────────────────
        doc.setFillColor(59, 26, 10);
        doc.rect(0, 0, pageW, 28, 'F');

        doc.setFont('times', 'bolditalic');
        doc.setFontSize(20);
        doc.setTextColor(196, 148, 58);
        doc.text('Karavali Lodge', pageW / 2, 13, { align: 'center' });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(200, 180, 140);
        doc.text('K.S. Rao Road, Mangalore - 575001  |  0824-2441104  |  enquiry@karavalilodge.com', pageW / 2, 21, { align: 'center' });

        y = 34;

        // ── TAX INVOICE title ─────────────────────────────────────
        doc.setFillColor(245, 240, 232);
        doc.rect(margin, y, contentW, 8, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.setTextColor(59, 26, 10);
        doc.text('TAX INVOICE', pageW / 2, y + 5.5, { align: 'center' });
        y += 12;

        // ── Bill meta row ─────────────────────────────────────────
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(80, 80, 80);
        doc.text(`Bill No: ${bill.billNo}`, margin, y);
        doc.text(`Date: ${Utils.formatDate(bill.createdAt)}`, pageW - margin, y, { align: 'right' });
        doc.text(`Status: ${bill.status}`, pageW - margin, y + 5, { align: 'right' });
        y += 12;

        // ── Divider ───────────────────────────────────────────────
        doc.setDrawColor(196, 148, 58);
        doc.setLineWidth(0.5);
        doc.line(margin, y, pageW - margin, y);
        y += 5;

        // ── Guest info & stay info (two columns) ──────────────────
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(59, 26, 10);
        doc.text('GUEST DETAILS', margin, y);
        doc.text('STAY DETAILS', pageW / 2 + 5, y);
        y += 5;

        doc.setFontSize(9);

        const leftCol  = [
            ['Guest Name', bill.guestName],
            ['Mobile',     bill.mobile || '-'],
            ['Room',       room ? `${room.roomNumber} (${room.roomType})` : '-'],
        ];
        const rightCol = [
            ['Check-In',  ciDisplay],
            ['Check-Out', coDisplay],
            ['Duration',  `${bill.nights} Night(s)`],
        ];

        leftCol.forEach((row, i) => {
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(59, 26, 10);
            doc.text(row[0] + ':', margin, y + i * 6);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(50, 50, 50);
            doc.text(row[1], margin + 26, y + i * 6);
        });
        rightCol.forEach((row, i) => {
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(59, 26, 10);
            doc.text(row[0] + ':', pageW / 2 + 5, y + i * 6);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(50, 50, 50);
            doc.text(row[1], pageW / 2 + 28, y + i * 6);
        });
        y += 22;

        // ── Divider ───────────────────────────────────────────────
        doc.setDrawColor(196, 148, 58);
        doc.line(margin, y, pageW - margin, y);
        y += 6;

        // ── Charges table header ──────────────────────────────────
        doc.setFillColor(59, 26, 10);
        doc.rect(margin, y, contentW, 7, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(255, 255, 255);
        doc.text('Description', margin + 3, y + 4.8);
        doc.text('Amount', pageW - margin - 3, y + 4.8, { align: 'right' });
        y += 10;

        // ── Charges rows ──────────────────────────────────────────
        const roomPriceDisplay = room ? cur(room.price) : 'Rs.0.00';
        const extraChargeAmt   = parseFloat(bill.extraCharge) || 0;
        const subtotal = (parseFloat(bill.roomCharges)    || 0)
                       + extraChargeAmt
                       + (parseFloat(bill.serviceCharges)  || 0)
                       + (parseFloat(bill.amenityCharges)  || 0);

        const chargeRows = [
            [`Room Charges (${bill.nights} night(s) x ${roomPriceDisplay})`, cur(bill.roomCharges), false],
            ...(extraChargeAmt > 0 ? [[
                `Extra Time Charge${bill.extraNote ? ': ' + bill.extraNote : ''}`,
                cur(extraChargeAmt),
                true   // flag = highlight row in orange
            ]] : []),
            ['Restaurant / Room Service Charges', cur(bill.serviceCharges || 0), false],
            ['Amenity / Extra Service Charges',   cur(bill.amenityCharges || 0), false],
        ];

        chargeRows.forEach((row, i) => {
            const isExtra = row[2] === true;
            const bg = isExtra ? [255, 249, 236] : (i % 2 === 0 ? [251, 247, 242] : [255, 255, 255]);
            doc.setFillColor(...bg);
            doc.rect(margin, y - 1, contentW, 7, 'F');
            doc.setFont('helvetica', isExtra ? 'bold' : 'normal');
            doc.setFontSize(9);
            doc.setTextColor(isExtra ? 200 : 50, isExtra ? 100 : 50, isExtra ? 0 : 50);
            // Truncate long extra note so it fits
            const label = row[0].length > 72 ? row[0].substring(0, 70) + '…' : row[0];
            doc.text(label, margin + 3, y + 4);
            doc.text(row[1], pageW - margin - 3, y + 4, { align: 'right' });
            y += 7;
        });

        y += 2;
        doc.setDrawColor(220, 210, 195);
        doc.setLineWidth(0.3);
        doc.line(margin, y, pageW - margin, y);
        y += 4;

        // ── Totals block ──────────────────────────────────────────
        const totalsRows = [
            ['Subtotal',     cur(subtotal),          false, false],
            ['GST (12%)',    cur(bill.tax),           false, false],
            ['Grand Total',  cur(bill.totalAmount),   true,  true ],
            ['Advance Paid', '- ' + cur(bill.advance || 0), false, false],
            ['Balance Due',  cur(bill.balance),       true,  false],
        ];

        totalsRows.forEach(([label, value, bold, highlight]) => {
            const rowH = highlight ? 8 : 7;
            if (highlight) {
                doc.setFillColor(59, 26, 10);
                doc.rect(margin, y - 1, contentW, rowH, 'F');
                doc.setTextColor(255, 255, 255);
            } else {
                doc.setFillColor(245, 240, 232);
                doc.rect(margin, y - 1, contentW, rowH, 'F');
                doc.setTextColor(50, 50, 50);
            }
            doc.setFont('helvetica', bold ? 'bold' : 'normal');
            doc.setFontSize(bold ? 10 : 9);
            doc.text(label, margin + 3, y + (highlight ? 4.5 : 4));
            doc.text(value, pageW - margin - 3, y + (highlight ? 4.5 : 4), { align: 'right' });
            y += rowH + 1;
        });

        y += 3;

        // ── Billing Policy box ────────────────────────────────────
        const policyBoxH = 34;
        doc.setFillColor(250, 247, 242);
        doc.rect(margin, y, contentW, policyBoxH, 'F');
        doc.setDrawColor(196, 148, 58);
        doc.setLineWidth(0.4);
        doc.rect(margin, y, contentW, policyBoxH, 'S');

        // Title row
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        doc.setTextColor(59, 26, 10);
        doc.text('Flexible Check-Out Billing Policy', margin + 3, y + 6);

        // Four-tier table inside the box
        const col1x = margin + 3;
        const col2x = margin + 52;
        const col3x = margin + 100;
        const col4x = margin + 145;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.setTextColor(59, 26, 10);
        doc.text('Stay Duration',    col1x, y + 13);
        doc.text('Billing Rule',     col2x, y + 13);
        doc.text('Stay Duration',    col3x, y + 13);
        doc.text('Billing Rule',     col4x, y + 13);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(80, 80, 80);
        doc.text('Within 24 hrs',    col1x, y + 20);
        doc.text('1 Night Charge',   col2x, y + 20);
        doc.text('Extra 6–12 hrs',   col3x, y + 20);
        doc.text('Half-Day Charge',  col4x, y + 20);
        doc.text('Extra 1–6 hrs',    col1x, y + 27);
        doc.text('Hourly (price÷24)',col2x, y + 27);
        doc.text('Extra 12+ hrs',    col3x, y + 27);
        doc.text('Full Extra Day',   col4x, y + 27);

        // Divider between the two columns
        doc.setDrawColor(220, 200, 170);
        doc.setLineWidth(0.2);
        doc.line(col3x - 4, y + 10, col3x - 4, y + policyBoxH - 4);

        // Footer note inside box
        doc.setFont('helvetica', 'italic');
        doc.setFontSize(6.8);
        doc.setTextColor(140, 120, 90);
        doc.text('All charges subject to GST @ 12%. Extra time charges apply beyond the free check-out window.', margin + 3, y + policyBoxH - 2);

        y += policyBoxH + 4;

        // ── Footer ────────────────────────────────────────────────
        doc.setFillColor(59, 26, 10);
        doc.rect(0, 282, pageW, 15, 'F');
        doc.setFont('times', 'italic');
        doc.setFontSize(9);
        doc.setTextColor(196, 148, 58);
        doc.text('Thank you for staying at Karavali Lodge!', pageW / 2, 288, { align: 'center' });
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(180, 160, 120);
        doc.text('Open 24/7  |  0824-2389156  |  enquiry@karavalilodge.com', pageW / 2, 293, { align: 'center' });

        // ── Save ──────────────────────────────────────────────────
        doc.save(`Invoice_${bill.billNo}_${bill.guestName.replace(/\s+/g, '_')}.pdf`);
        Utils.showToast('Bill downloaded as PDF', 'success');
    },
    markPaid(id) {
        const method = prompt('Payment method? (Cash / UPI / Card / Other)', 'Cash');
        if (method === null) return;
        const bill = DB.getById(DB.BILLS, id);
        DB.update(DB.BILLS, id, {
            status: 'Paid',
            balance: 0,
            paidAt: new Date().toISOString(),
            paymentMethod: method.trim() || 'Cash'
        });

        // Only free the room if the guest has actually checked out.
        // If an active check-in still exists the guest is still staying —
        // freeing the room here would cause a double-booking risk.
        if (bill && bill.roomId) {
            const activeCheckin = DB.query(DB.CHECKINS, c =>
                c.roomId === bill.roomId && c.status === 'Checked-In'
            );
            if (activeCheckin.length === 0) {
                // No active guest — safe to mark Available
                DB.update(DB.ROOMS, bill.roomId, { status: 'Available' });
            }
            // If guest is still checked in, room stays Occupied and will be
            // set to Cleaning automatically when check-out is processed
        }

        Utils.showToast('Bill marked as paid');
        LodgeApp.navigate('billing');
    },

    printBill(id) {
        // Open the modal first, then print once it's fully shown
        this.viewBill(id);
        const modalEl = document.getElementById('billViewModal');
        modalEl.addEventListener('shown.bs.modal', function onShown() {
            modalEl.removeEventListener('shown.bs.modal', onShown);
            setTimeout(() => window.print(), 200);
        });
    },

    filterBills() {
        const search = (document.getElementById('billSearch').value || '').toLowerCase();
        const statusFilter = document.getElementById('billStatusFilter').value;

        document.querySelectorAll('#billTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchSearch = !search || text.includes(search);
            const matchStatus = !statusFilter || text.includes(statusFilter.toLowerCase());
            row.style.display = (matchSearch && matchStatus) ? '' : 'none';
        });
    }
};
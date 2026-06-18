/* ==========================================
   Guest Management Module
   ========================================== */
const GuestModule = {
    render() {
        const guests = DB.getAll(DB.GUESTS);
        const bookings = DB.getAll(DB.BOOKINGS);

        return `
            <div class="filter-bar">
                <input type="text" class="form-control" id="guestSearch" placeholder="Search guests..." oninput="GuestModule.filterGuests()">
                <button class="btn btn-accent ms-auto" onclick="GuestModule.showForm()">
                    <i class="bi bi-plus-lg"></i> Add Guest
                </button>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${guests.length}</div>
                                <div class="stat-label">Total Guests</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${guests.filter(g => {
                                    const gBookings = bookings.filter(b => b.mobile === g.mobile);
                                    return gBookings.length > 1;
                                }).length}</div>
                                <div class="stat-label">Repeat Customers</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-repeat"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${DB.getAll(DB.ID_PROOFS).length}</div>
                                <div class="stat-label">ID Proofs on File</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-badge"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="module-card">
                <div class="card-header">
                    <span><i class="bi bi-people me-2"></i>Guest Directory</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table data-table" id="guestTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Address</th>
                                    <th>Nationality</th>
                                    <th>ID Proof</th>
                                    <th>Stays</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${guests.length === 0 ? '<tr><td colspan="8" class="text-center py-4 text-muted">No guests found</td></tr>' :
                                guests.map(g => {
                                    const stayCount = bookings.filter(b => b.mobile === g.mobile).length;
                                    const idProof = DB.query(DB.ID_PROOFS, ip => ip.guestId === g.id);
                                    const hasIdRecord = idProof.length > 0;
                                    const hasPhoto    = hasIdRecord && (idProof[0].photo || idProof[0].photoBack);
                                    const idBadge = !hasIdRecord
                                        ? '<span class="badge bg-secondary">Not uploaded</span>'
                                        : hasPhoto
                                            ? '<span class="badge bg-success"><i class="bi bi-check me-1"></i>Verified</span>'
                                            : '<span class="badge bg-warning text-dark" title="ID number collected but photo not yet uploaded"><i class="bi bi-clock me-1"></i>Photo Pending</span>';
                                    return `
                                    <tr>
                                        <td><strong>${Utils.escapeHtml(g.name)}</strong></td>
                                        <td>${Utils.escapeHtml(g.mobile || '-')}</td>
                                        <td>${Utils.escapeHtml(g.email || '-')}</td>
                                        <td><small>${Utils.escapeHtml(g.address || '-')}</small></td>
                                        <td>${Utils.escapeHtml(g.nationality || 'Indian')}</td>
                                        <td>${idBadge}</td>
                                        <td><span class="badge bg-primary">${stayCount}</span></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="GuestModule.showForm('${g.id}')" title="Edit"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-outline-info" onclick="GuestModule.viewHistory('${g.id}')" title="History"><i class="bi bi-clock-history"></i></button>
                                                <a class="btn btn-outline-success" href="tel:${Utils.escapeHtml(g.mobile || '')}" title="Call Guest"><i class="bi bi-telephone-fill"></i></a>
                                                <button class="btn btn-outline-danger" onclick="GuestModule.deleteGuest('${g.id}')" title="Delete"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Guest Form Modal -->
            <div class="modal fade" id="guestModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="guestModalTitle">Add Guest</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="guestForm">
                                <input type="hidden" name="id" id="guestId">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mobile *</label>
                                        <input type="tel" class="form-control" name="mobile" required maxlength="15">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nationality</label>
                                        <input type="text" class="form-control" name="nationality" value="Indian" maxlength="50">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2" maxlength="300"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Proof Type</label>
                                        <select class="form-select" name="idProofType">
                                            <option value="">Select</option>
                                            ${Utils.idProofTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Proof Number</label>
                                        <input type="text" class="form-control" name="idProofNumber" maxlength="30">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="GuestModule.saveGuest()">Save Guest</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guest History Modal -->
            <div class="modal fade" id="guestHistoryModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Guest History</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="guestHistoryContent">
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    showForm(id) {
        const modal = new bootstrap.Modal(document.getElementById('guestModal'));
        const form = document.getElementById('guestForm');
        form.reset();
        if (id) {
            const guest = DB.getById(DB.GUESTS, id);
            if (guest) {
                document.getElementById('guestModalTitle').textContent = 'Edit Guest';
                Utils.populateForm('guestForm', guest);
            }
        } else {
            document.getElementById('guestModalTitle').textContent = 'Add Guest';
            document.getElementById('guestId').value = '';
        }
        modal.show();
    },

    saveGuest() {
        const data = Utils.getFormData('guestForm');
        if (!data.name || !data.mobile) {
            Utils.showToast('Name and mobile are required', 'warning');
            return;
        }

        if (data.id) {
            DB.update(DB.GUESTS, data.id, data);
            Utils.showToast('Guest updated successfully');
        } else {
            delete data.id;
            DB.add(DB.GUESTS, data);
            Utils.showToast('Guest added successfully');
        }
        bootstrap.Modal.getInstance(document.getElementById('guestModal')).hide();
        LodgeApp.navigate('guests');
    },

    deleteGuest(id) {
        if (!Utils.confirmAction('Delete this guest record?')) return;
        DB.remove(DB.GUESTS, id);
        Utils.showToast('Guest deleted', 'info');
        LodgeApp.navigate('guests');
    },

    viewHistory(id) {
        const guest = DB.getById(DB.GUESTS, id);
        if (!guest) return;
        const bookings = DB.query(DB.BOOKINGS, b => b.mobile === guest.mobile);
        const bills = DB.query(DB.BILLS, b => b.guestId === id);

        let html = `<h5>${Utils.escapeHtml(guest.name)}</h5><p class="text-muted">${Utils.escapeHtml(guest.mobile)}</p>`;
        html += `<h6 class="mt-3">Booking History (${bookings.length})</h6>`;
        if (bookings.length === 0) {
            html += '<p class="text-muted">No bookings found</p>';
        } else {
            html += '<table class="table table-sm"><thead><tr><th>Date</th><th>Room</th><th>Check-In</th><th>Check-Out</th><th>Status</th></tr></thead><tbody>';
            bookings.forEach(b => {
                const room = DB.getById(DB.ROOMS, b.roomId);
                html += `<tr><td>${Utils.formatDate(b.createdAt)}</td><td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td><td>${Utils.formatDate(b.checkIn)}</td><td>${Utils.formatDate(b.checkOut)}</td><td>${Utils.statusBadge(b.status)}</td></tr>`;
            });
            html += '</tbody></table>';
        }

        html += `<h6 class="mt-3">Billing History (${bills.length})</h6>`;
        if (bills.length === 0) {
            html += '<p class="text-muted">No bills found</p>';
        } else {
            html += '<table class="table table-sm"><thead><tr><th>Bill No</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
            bills.forEach(b => {
                html += `<tr><td>${Utils.escapeHtml(b.billNo)}</td><td>${Utils.formatDate(b.createdAt)}</td><td>${Utils.formatCurrency(b.totalAmount)}</td><td>${Utils.statusBadge(b.status)}</td></tr>`;
            });
            html += '</tbody></table>';
        }

        document.getElementById('guestHistoryContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('guestHistoryModal')).show();
    },

    filterGuests() {
        const search = (document.getElementById('guestSearch').value || '').toLowerCase();
        document.querySelectorAll('#guestTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = (!search || text.includes(search)) ? '' : 'none';
        });
    }
};
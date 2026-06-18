/* ==========================================
   Booking / Reservation Module
   ========================================== */
let _bookingSaving = false;

const BookingModule = {
    render() {
        const bookings = DB.getAll(DB.BOOKINGS);
        const rooms = DB.getAll(DB.ROOMS);
        const availableRooms = rooms.filter(r => !['Maintenance','Out of Order'].includes(r.status || 'Available'));

        return `
            <div class="filter-bar">
                <input type="text" class="form-control" id="bookingSearch" placeholder="Search bookings..." oninput="BookingModule.filterBookings()">
                <select class="form-select" id="bookingStatusFilter" onchange="BookingModule.filterBookings()">
                    <option value="">All Status</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Checked-In">Checked-In</option>
                    <option value="Completed">Completed</option>
                </select>
                <select class="form-select" id="bookingTypeFilter" onchange="BookingModule.filterBookings()">
                    <option value="">All Types</option>
                    ${Utils.bookingTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                </select>
                <button class="btn btn-accent ms-auto" onclick="BookingModule.showForm()">
                    <i class="bi bi-plus-lg"></i> New Booking
                </button>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bookings.length}</div>
                                <div class="stat-label">Total Bookings</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bookings.filter(b => b.status === 'Confirmed').length}</div>
                                <div class="stat-label">Confirmed</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bookings.filter(b => b.status === 'Pending').length}</div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${availableRooms.length}</div>
                                <div class="stat-label">Available Rooms</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-door-open"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="module-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-list-ul me-2"></i>Bookings</span>
                    <select class="form-select form-select-sm" id="bookingPeriodFilter" onchange="BookingModule.filterBookings()" style="width:auto;min-width:150px;">
                        <option value="">All Time</option>
                        <option value="30">Past 30 Days</option>
                        <option value="90">Past 90 Days</option>
                        <option value="365">Past 1 Year</option>
                    </select>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table data-table" id="bookingTable">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Guest Name</th>
                                    <th>Mobile</th>
                                    <th>Room</th>
                                    <th>Type</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Guests</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${bookings.length === 0 ? '<tr><td colspan="10" class="text-center py-4 text-muted">No bookings found</td></tr>' :
                                bookings.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).map(b => {
                                    const room = DB.getById(DB.ROOMS, b.roomId);
                                    return `
                                    <tr data-checkin="${b.checkIn}" data-status="${(b.status||'Pending').toLowerCase().replace(/[\s\-\/]/g,'')}">
                                        <td><strong>${Utils.escapeHtml(b.bookingNo || b.id.substr(0,8).toUpperCase())}</strong></td>
                                        <td>${Utils.escapeHtml(b.guestName)}</td>
                                        <td>${Utils.escapeHtml(b.mobile)}</td>
                                        <td>${Utils.escapeHtml(b.roomNumber || (DB.getById(DB.ROOMS, b.roomId) ? DB.getById(DB.ROOMS, b.roomId).roomNumber : '-'))}</td>
                                        <td><small>${Utils.escapeHtml(b.bookingType)}</small></td>
                                        <td>
                                            ${Utils.formatDate(b.checkIn)}
                                            ${b.checkInTime ? `<br><small style="color:#C4943A"><i class="bi bi-person-clock me-1"></i>Req: ${b.checkInTime}</small>` : ''}
                                            ${(() => {
                                                const ci = DB.query(DB.CHECKINS, c => c.bookingId === b.id)[0];
                                                if (ci && ci.checkInTime) {
                                                    const t = new Date(ci.checkInTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
                                                    return `<br><small class="text-success"><i class="bi bi-box-arrow-in-right me-1"></i>In: ${t}</small>`;
                                                }
                                                return '';
                                            })()}
                                        </td>
                                        <td>
                                            ${Utils.formatDate(b.checkOut)}
                                            ${b.checkOutTime ? `<br><small style="color:#C4943A"><i class="bi bi-person-clock me-1"></i>Req: ${b.checkOutTime}</small>` : ''}
                                            ${(() => {
                                                const ci = DB.query(DB.CHECKINS, c => c.bookingId === b.id)[0];
                                                if (ci && ci.checkOutTime) {
                                                    const t = new Date(ci.checkOutTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
                                                    return `<br><small class="text-danger"><i class="bi bi-box-arrow-right me-1"></i>Out: ${t}</small>`;
                                                }
                                                return '';
                                            })()}
                                        </td>
                                        <td>${b.numGuests || 1}</td>
                                        <td>${Utils.statusBadge(b.status)}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="BookingModule.showForm('${b.id}')" title="Edit"><i class="bi bi-pencil"></i></button>
                                                ${b.status !== 'Cancelled' && b.status !== 'Completed' ? `<button class="btn btn-outline-danger" onclick="BookingModule.cancelBooking('${b.id}')" title="Cancel"><i class="bi bi-x-circle"></i></button>` : ''}
                                                <button class="btn btn-outline-dark" onclick="BookingModule.deleteBooking('${b.id}')" title="Delete Permanently"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Booking Form Modal -->
            <div class="modal fade" id="bookingModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bookingModalTitle">New Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="bookingForm">
                                <input type="hidden" name="id" id="bookingId">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Guest Name *</label>
                                        <input type="text" class="form-control" name="guestName" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mobile Number *</label>
                                        <input type="tel" class="form-control" name="mobile" required maxlength="15" pattern="[0-9+\\-\\s]{7,15}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" maxlength="100" placeholder="guest@email.com">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Nationality</label>
                                        <input type="text" class="form-control" name="nationality" maxlength="50" value="Indian">
                                    </div>
                                    <div class="col-md-9">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" maxlength="200" placeholder="City, State">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Booking Type *</label>
                                        <select class="form-select" name="bookingType" required>
                                            ${Utils.bookingTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Room Type *</label>
                                        <select class="form-select" name="roomType" required onchange="BookingModule.updateAvailableRooms()">
                                            <option value="">Select Type</option>
                                            ${Utils.roomTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Room *</label>
                                        <select class="form-select" name="roomId" id="bookingRoomSelect" required onchange="BookingModule.onRoomChange()">
                                            <option value="">Select Room</option>
                                            ${availableRooms.map(r => `<option value="${r.id}" data-capacity="${r.capacity || 2}">${r.roomNumber} - ${r.roomType} (${Utils.formatCurrency(r.price)})</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-In Date *</label>
                                        <input type="date" class="form-control" name="checkIn" required min="${Utils.today()}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Check-In Time</label>
                                        <input type="time" class="form-control" name="checkInTime" value="12:00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-Out Date *</label>
                                        <input type="date" class="form-control" name="checkOut" required min="${Utils.today()}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Check-Out Time</label>
                                        <input type="time" class="form-control" name="checkOutTime" value="11:00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">No. of Guests <small class="text-muted" id="guestCapacityHint"></small></label>
                                        <input type="number" class="form-control" name="numGuests" id="bookingNumGuests" min="1" max="20" value="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="Confirmed">Confirmed</option>
                                            <option value="Pending">Pending</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Advance Amount (₹)</label>
                                        <input type="number" class="form-control" name="advance" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Special Requests</label>
                                        <textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="BookingModule.saveBooking()">Save Booking</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    showForm(id) {
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        const form = document.getElementById('bookingForm');
        form.reset();
        if (id) {
            const booking = DB.getById(DB.BOOKINGS, id);
            if (booking) {
                document.getElementById('bookingModalTitle').textContent = 'Edit Booking';
                Utils.populateForm('bookingForm', booking);

                // ── Fix 1: Restore Room Type ────────────────────────────
                if (booking.roomType && form.elements.roomType) {
                    form.elements.roomType.value = booking.roomType;
                }

                // ── Fix 2: Rebuild room dropdown including the booked room ──
                // When editing, include ALL rooms (not just Available)
                // so the currently booked room appears as an option
                const roomSel = document.getElementById('bookingRoomSelect');
                const allRooms = DB.getAll(DB.ROOMS).filter(r =>
                    (r.status || 'Available') === 'Available' ||
                    r.id === booking.roomId  // always include the currently booked room
                );
                roomSel.innerHTML = '<option value="">Select Room</option>' +
                    allRooms.map(r => {
                        const label = `${Utils.escapeHtml(r.roomNumber)} - ${Utils.escapeHtml(r.roomType)} (${Utils.formatCurrency(r.price)})`;
                        return `<option value="${r.id}" data-capacity="${r.capacity || 2}"
                                        ${r.id === booking.roomId ? 'selected' : ''}>${label}</option>`;
                    }).join('');
                this.onRoomChange();

                // ── Fix 3: Restore address from guest record if not on booking ──
                if (!booking.address && form.elements.address) {
                    const guest = DB.query(DB.GUESTS, g => g.mobile === booking.mobile)[0];
                    if (guest && guest.address) {
                        form.elements.address.value = guest.address;
                    }
                }

                // ── Fix 4: Restore email from guest record if not on booking ──
                if (!booking.email && form.elements.email) {
                    const guest = DB.query(DB.GUESTS, g => g.mobile === booking.mobile)[0];
                    if (guest && guest.email) {
                        form.elements.email.value = guest.email;
                    }
                }

                // ── Fix 5: Restore time fields ──────────────────────────
                if (booking.checkInTime  && form.elements.checkInTime)
                    form.elements.checkInTime.value  = booking.checkInTime;
                if (booking.checkOutTime && form.elements.checkOutTime)
                    form.elements.checkOutTime.value = booking.checkOutTime;
            }
        } else {
            document.getElementById('bookingModalTitle').textContent = 'New Booking';
            document.getElementById('bookingId').value = '';
        }
        modal.show();
    },

    updateAvailableRooms() {
        const form = document.getElementById('bookingForm');
        const roomType = form.elements.roomType.value;
        const select = document.getElementById('bookingRoomSelect');
        const rooms = DB.getAll(DB.ROOMS).filter(r =>
            !['Maintenance','Out of Order'].includes(r.status || 'Available') && (!roomType || r.roomType === roomType)
        );
        select.innerHTML = '<option value="">Select Room</option>' +
            rooms.map(r => `<option value="${r.id}" data-capacity="${r.capacity || 2}">${Utils.escapeHtml(r.roomNumber)} - ${Utils.escapeHtml(r.roomType)} (${Utils.formatCurrency(r.price)})</option>`).join('');
        this.onRoomChange();
    },

    onRoomChange() {
        const select = document.getElementById('bookingRoomSelect');
        const guestsInput = document.getElementById('bookingNumGuests');
        const hint = document.getElementById('guestCapacityHint');
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            const capacity = parseInt(selected.getAttribute('data-capacity')) || 2;
            guestsInput.max = capacity;
            if (parseInt(guestsInput.value) > capacity) {
                guestsInput.value = capacity;
            }
            hint.textContent = '(max ' + capacity + ')';
        } else {
            guestsInput.max = 20;
            hint.textContent = '';
        }    },

    saveBooking() {
        if (_bookingSaving) return;
        _bookingSaving = true;

        const data = Utils.getFormData('bookingForm');
        if (!data.guestName || !data.mobile || !data.roomId || !data.checkIn || !data.checkOut) {
            Utils.showToast('Please fill all required fields', 'warning');
            _bookingSaving = false;
            return;
        }
        if (new Date(data.checkOut) <= new Date(data.checkIn)) {
            Utils.showToast('Check-out must be after check-in', 'warning');
            return;
        }
        data.advance = parseFloat(data.advance) || 0;
        data.numGuests = parseInt(data.numGuests) || 1;

        // Validate guests against room capacity
        const room = DB.getById(DB.ROOMS, data.roomId);
        if (room) {
            const capacity = parseInt(room.capacity) || 2;
            if (data.numGuests > capacity) {
                Utils.showToast(`Room ${room.roomNumber} capacity is ${capacity}. Cannot book ${data.numGuests} guests.`, 'warning');
                _bookingSaving = false;
                return;
            }
            // Save room number with booking
            data.roomNumber = room.roomNumber;
        }

        data.bookingNo = data.bookingNo || 'BK-' + Date.now().toString().substr(-6);

        if (data.id) {
            // If room changed on edit, free the old room
            const existingBooking = DB.getById(DB.BOOKINGS, data.id);
            if (existingBooking && existingBooking.roomId && existingBooking.roomId !== data.roomId) {
                DB.update(DB.ROOMS, existingBooking.roomId, { status: 'Available' });
                DB.update(DB.ROOMS, data.roomId, { status: 'Reserved' });
            }
            DB.update(DB.BOOKINGS, data.id, data);
            Utils.showToast('Booking updated successfully');
        } else {
            delete data.id;

            // ── Auto-create or update guest record ───────────────
            const existingGuest = DB.query(DB.GUESTS, g => g.mobile === data.mobile);
            if (existingGuest.length === 0) {
                // New guest — save all details from the booking form
                DB.add(DB.GUESTS, {
                    name:        data.guestName,
                    mobile:      data.mobile,
                    email:       data.email       || '',
                    address:     data.address     || '',
                    nationality: data.nationality || 'Indian',
                });
            } else {
                // Existing guest — update email/address if newly provided
                const g       = existingGuest[0];
                const updates = {};
                if (data.email   && !g.email)   updates.email   = data.email;
                if (data.address && !g.address)  updates.address = data.address;
                if (Object.keys(updates).length > 0) {
                    DB.update(DB.GUESTS, g.id, updates);
                }
            }

            // Reserve the room
            DB.update(DB.ROOMS, data.roomId, { status: 'Reserved' });
            DB.add(DB.BOOKINGS, data);
            Utils.showToast('Booking created successfully');
        }
        _bookingSaving = false;
        bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
        LodgeApp.navigate('booking');
    },

    cancelBooking(id) {
        if (!Utils.confirmAction('Cancel this booking?')) return;
        const booking = DB.getById(DB.BOOKINGS, id);
        if (booking) {
            DB.update(DB.BOOKINGS, id, { status: 'Cancelled' });
            // Free up the room
            if (booking.roomId) {
                DB.update(DB.ROOMS, booking.roomId, { status: 'Available' });
            }
            Utils.showToast('Booking cancelled');
            LodgeApp.navigate('booking');
        }
    },

    deleteBooking(id) {
        const booking = DB.getById(DB.BOOKINGS, id);
        if (!booking) return;
        const activeStatuses = ['Confirmed', 'Pending', 'Checked-In'];
        const isActive = activeStatuses.includes(booking.status);
        const msg = isActive
            ? `⚠️ This booking is currently "${booking.status}".\n\nDeleting it will permanently remove it and cannot be undone.\n\nAre you sure?`
            : `Permanently delete this booking for ${booking.guestName}?\n\nThis cannot be undone.`;
        if (!Utils.confirmAction(msg)) return;
        if (isActive && booking.roomId) {
            DB.update(DB.ROOMS, booking.roomId, { status: 'Available' });
        }
        DB.remove(DB.BOOKINGS, id);
        Utils.showToast('Booking deleted permanently', 'info');
        LodgeApp.navigate('booking');
    },

    filterBookings() {
        const search = (document.getElementById('bookingSearch').value || '').toLowerCase();
        const statusFilter = document.getElementById('bookingStatusFilter').value;
        const typeFilter = document.getElementById('bookingTypeFilter').value;
        const periodFilter = document.getElementById('bookingPeriodFilter').value;
        const normStatus = statusFilter.toLowerCase().replace(/[\s\-\/]/g, '');

        let cutoffDate = null;
        if (periodFilter) {
            cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - parseInt(periodFilter));
        }

        document.querySelectorAll('#bookingTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status') || '';
            const matchSearch = !search || text.includes(search);
            const matchStatus = !normStatus || rowStatus === normStatus;
            const matchType = !typeFilter || text.includes(typeFilter.toLowerCase());

            let matchPeriod = true;
            if (cutoffDate) {
                const rawDate = row.getAttribute('data-checkin');
                if (rawDate) {
                    const parsed = new Date(rawDate);
                    matchPeriod = !isNaN(parsed) && parsed >= cutoffDate;
                }
            }

            row.style.display = (matchSearch && matchStatus && matchType && matchPeriod) ? '' : 'none';
        });
    }
};
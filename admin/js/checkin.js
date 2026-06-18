/* ==========================================
   Check-In / Check-Out Module
   ========================================== */
let _checkinSaving = false;

const CheckInModule = {
    render() {
        const checkins = DB.getAll(DB.CHECKINS);
        const activeCheckins = checkins.filter(c => c.status === 'Checked-In');
        const rooms = DB.getAll(DB.ROOMS);
        const bookings = DB.getAll(DB.BOOKINGS).filter(b => b.status === 'Confirmed' || b.status === 'Pending');

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${activeCheckins.length}</div>
                                <div class="stat-label">Currently Checked-In</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-box-arrow-in-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${bookings.length}</div>
                                <div class="stat-label">Pending Arrivals</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${checkins.filter(c => c.status === 'Checked-Out' && c.checkOutDate === Utils.today()).length}</div>
                                <div class="stat-label">Today's Check-Outs</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-box-arrow-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${rooms.filter(r => !['Maintenance','Out of Order','Occupied','Reserved','Cleaning'].includes(r.status || 'Available')).length}</div>
                                <div class="stat-label">Available Rooms</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-door-open"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-box-arrow-in-right me-2"></i>Quick Check-In</span>
                        </div>
                        <div class="card-body">
                            <form id="checkInForm">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Select Booking (or Walk-In)</label>
                                        <select class="form-select" name="bookingId" id="checkInBooking" onchange="CheckInModule.loadBookingData()">
                                            <option value="">-- Walk-In (No Booking) --</option>
                                            ${bookings.map(b => {
                                                const room = DB.getById(DB.ROOMS, b.roomId);
                                                return `<option value="${b.id}">${Utils.escapeHtml(b.guestName)} - ${room ? Utils.escapeHtml(room.roomNumber) : 'No Room'} (${Utils.formatDate(b.checkIn)})</option>`;
                                            }).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Guest Name *</label>
                                        <input type="text" class="form-control" name="guestName" required maxlength="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mobile *</label>
                                        <input type="tel" class="form-control" name="mobile" required maxlength="15">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" maxlength="100" placeholder="guest@email.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" maxlength="200" placeholder="City, State">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Room *</label>
                                        <select class="form-select" name="roomId" id="checkInRoom" required>
                                            <option value="">Select Room</option>
                                            ${rooms.filter(r => r.status === 'Available' || r.status === 'Reserved').map(r =>
                                                `<option value="${r.id}" data-capacity="${r.capacity || 2}">${Utils.escapeHtml(r.roomNumber)} - ${Utils.escapeHtml(r.roomType)} (${Utils.formatCurrency(r.price)})</option>`
                                            ).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Expected Check-Out</label>
                                        <input type="date" class="form-control" name="expectedCheckOut" min="${Utils.today()}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Proof Type <span class="text-danger">*</span></label>
                                        <select class="form-select" name="idProofType" required
                                                style="border-color:#dee2e6">
                                            <option value="">-- Select ID Type --</option>
                                            ${Utils.idProofTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="idNumber" maxlength="30"
                                               placeholder="Enter ID number" required>
                                    </div>
                                    <div class="col-12">
                                        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;
                                                    padding:10px 14px;font-size:0.83rem;color:#856404;display:flex;
                                                    align-items:center;gap:8px;">
                                            <i class="bi bi-shield-exclamation" style="font-size:1rem;flex-shrink:0"></i>
                                            <span><strong>ID proof is mandatory</strong> as per hotel policy and government regulations.
                                            Check-in will be blocked without a valid government-issued photo ID.</span>
                                        </div>
                                    </div>

                                    <!-- ── Mandatory ID Photo Upload ── -->
                                    <div class="col-12">
                                        <div style="background:#f0f7ff;border:1px solid #b8d4f0;border-radius:10px;padding:14px 16px;">
                                            <div style="font-weight:600;color:#3B1A0A;font-size:0.85rem;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                                <i class="bi bi-camera-fill" style="color:#C4943A"></i>
                                                ID Photo Upload <span class="text-danger ms-1">* Required</span>
                                                <small class="text-muted fw-normal ms-1">— scan and upload before check-in</small>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label" style="font-size:0.8rem;font-weight:600;">Front Side <span class="text-danger">*</span></label>
                                                    <div id="ciPhotoFrontBox" onclick="document.getElementById('ciPhotoFront').click()"
                                                         style="border:2px dashed #C4943A;border-radius:8px;height:100px;display:flex;flex-direction:column;
                                                                align-items:center;justify-content:center;cursor:pointer;background:#fdfaf5;
                                                                transition:all 0.2s;position:relative;overflow:hidden;">
                                                        <div id="ciPhotoFrontPlaceholder" style="text-align:center;">
                                                            <i class="bi bi-cloud-arrow-up" style="font-size:1.5rem;color:#C4943A;display:block;margin-bottom:4px;"></i>
                                                            <small class="text-muted">Click to upload front</small>
                                                        </div>
                                                        <img id="ciPhotoFrontPreview" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                                                    </div>
                                                    <input type="file" id="ciPhotoFront" accept="image/jpeg,image/png,image/jpg" style="display:none"
                                                           onchange="CheckInModule.previewPhoto(this,'ciPhotoFrontPreview','ciPhotoFrontPlaceholder','ciPhotoFrontBox')">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label" style="font-size:0.8rem;font-weight:600;">Back Side <span class="text-muted fw-normal">(optional)</span></label>
                                                    <div id="ciPhotoBackBox" onclick="document.getElementById('ciPhotoBack').click()"
                                                         style="border:2px dashed #ccc;border-radius:8px;height:100px;display:flex;flex-direction:column;
                                                                align-items:center;justify-content:center;cursor:pointer;background:#fafafa;
                                                                transition:all 0.2s;position:relative;overflow:hidden;">
                                                        <div id="ciPhotoBackPlaceholder" style="text-align:center;">
                                                            <i class="bi bi-cloud-arrow-up" style="font-size:1.5rem;color:#aaa;display:block;margin-bottom:4px;"></i>
                                                            <small class="text-muted">Click to upload back</small>
                                                        </div>
                                                        <img id="ciPhotoBackPreview" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                                                    </div>
                                                    <input type="file" id="ciPhotoBack" accept="image/jpeg,image/png,image/jpg" style="display:none"
                                                           onchange="CheckInModule.previewPhoto(this,'ciPhotoBackPreview','ciPhotoBackPlaceholder','ciPhotoBackBox')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Advance Payment (₹)</label>
                                        <input type="number" class="form-control" name="advance" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Number of Guests
                                            <small class="text-muted" id="guestCapHint"></small>
                                        </label>
                                        <input type="number" class="form-control" name="numGuests"
                                               id="numGuestsInput" min="1" max="20" value="1"
                                               oninput="CheckInModule.validateGuestCount()"
                                               onchange="CheckInModule.validateGuestCount()">
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-accent w-100" onclick="CheckInModule.processCheckIn()">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Process Check-In
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" id="section-checkedin-guests">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-people me-2"></i>Currently Checked-In Guests</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Guest</th>
                                            <th>Room</th>
                                            <th>Check-In</th>
                                            <th>Days</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${activeCheckins.length === 0 ? '<tr><td colspan="5" class="text-center py-4 text-muted">No active check-ins</td></tr>' :
                                        activeCheckins.map(c => {
                                            const room = DB.getById(DB.ROOMS, c.roomId);
                                            const days = Utils.daysBetween(c.checkInDate, Utils.today());
                                            // Format check-in time
                                            const ciTime = c.checkInTime
                                                ? new Date(c.checkInTime).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})
                                                : '';
                                            return `
                                            <tr>
                                                <td><strong>${Utils.escapeHtml(c.guestName)}</strong><br><small class="text-muted">${Utils.escapeHtml(c.mobile)}</small></td>
                                                <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                <td>
                                                    ${Utils.formatDate(c.checkInDate)}
                                                    ${ciTime ? `<br><small class="text-muted"><i class="bi bi-clock me-1"></i>${ciTime}</small>` : ''}
                                                </td>
                                                <td>${days}</td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <a href="tel:${Utils.escapeHtml(c.mobile || '')}"
                                                           class="btn btn-sm btn-success d-flex align-items-center justify-content-center"
                                                           title="Call ${Utils.escapeHtml(c.guestName)} · ${Utils.escapeHtml(c.mobile || '')}"
                                                           style="width:36px;height:32px;padding:0;border-radius:6px;">
                                                            <i class="bi bi-telephone-fill" style="font-size:0.85rem"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-warning d-flex align-items-center gap-1"
                                                                onclick="CheckInModule.processCheckOut('${c.id}')"
                                                                style="height:32px;border-radius:6px;font-weight:600;font-size:0.82rem;white-space:nowrap">
                                                            <i class="bi bi-box-arrow-right"></i> Check-Out
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>`;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check-Out Modal -->
            <div class="modal fade" id="checkOutModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title"><i class="bi bi-box-arrow-right me-2"></i>Check-Out & Final Bill</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="checkOutContent">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-warning" id="confirmCheckOutBtn">
                                <i class="bi bi-check-circle me-1"></i>Confirm Check-Out & Generate Bill
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    onRoomChange() {
        const roomSel = document.getElementById('checkInRoom');
        const opt = roomSel ? roomSel.options[roomSel.selectedIndex] : null;
        const cap = opt ? parseInt(opt.getAttribute('data-capacity') || 20) : 20;
        const hint = document.getElementById('guestCapHint');
        const input = document.getElementById('numGuestsInput');
        if (hint) hint.textContent = cap ? `(max ${cap})` : '';
        if (input) {
            input.max = cap;
            // Clamp current value if over capacity
            if (parseInt(input.value) > cap) input.value = cap;
        }
    },

    validateGuestCount() {
        const roomSel = document.getElementById('checkInRoom');
        const opt = roomSel ? roomSel.options[roomSel.selectedIndex] : null;
        const cap = opt ? parseInt(opt.getAttribute('data-capacity') || 20) : 20;
        const input = document.getElementById('numGuestsInput');
        if (!input) return;
        const val = parseInt(input.value) || 1;
        if (val < 1) {
            input.value = 1;
        } else if (val > cap) {
            input.value = cap; // Hard-clamp to room capacity
            input.style.borderColor = '#dc3545';
            input.style.background  = '#fff5f5';
            setTimeout(() => {
                input.style.borderColor = '';
                input.style.background  = '';
            }, 2500);
            Utils.showToast(`Room ${opt ? opt.text.split('-')[0].trim() : ''} max capacity is ${cap} guest${cap > 1 ? 's' : ''}. Value clamped to ${cap}.`, 'warning');
        } else {
            input.style.borderColor = '';
            input.style.background  = '';
        }
        // Update max attribute
        input.max = cap;
    },

    loadBookingData() {
        const bookingId = document.getElementById('checkInBooking').value;
        const form = document.getElementById('checkInForm');

        if (!bookingId) {
            // Reset all fields when Walk-In selected
            form.reset();
            return;
        }

        const booking = DB.getById(DB.BOOKINGS, bookingId);
        if (!booking) return;

        // ── Fill booking fields ───────────────────────────────────
        form.elements.guestName.value        = booking.guestName  || '';
        form.elements.mobile.value           = booking.mobile     || '';
        form.elements.roomId.value           = booking.roomId     || '';
        form.elements.expectedCheckOut.value = booking.checkOut   || '';
        form.elements.numGuests.value = booking.numGuests || 1;

        // Update room capacity hint and enforce max after room loads
        setTimeout(() => {
            CheckInModule.onRoomChange();
            CheckInModule.validateGuestCount(); // clamp if booking numGuests > room capacity
        }, 100);
        form.elements.advance.value          = booking.advance    || 0;

        // ── Auto-fill guest details from Guest record ────────────
        const guest = DB.query(DB.GUESTS, g => g.mobile === booking.mobile)[0];
        if (guest) {
            if (form.elements.email   && guest.email)   form.elements.email.value   = guest.email;
            if (form.elements.address && guest.address) form.elements.address.value = guest.address;
        }

        // ── Auto-fill ID proof from ID_PROOFS record ─────────────
        if (guest) {
            const idProof = DB.query(DB.ID_PROOFS, p => p.guestId === guest.id)[0];
            if (idProof) {
                const idTypeEl   = form.elements.idProofType;
                const idNumEl    = form.elements.idNumber;
                if (idTypeEl && idProof.idType)   idTypeEl.value  = idProof.idType;
                if (idNumEl  && idProof.idNumber) idNumEl.value   = idProof.idNumber;

                // ── Auto-fill ID photos from existing record ──────────
                // The guest already uploaded their ID photo during online
                // booking — show it in the preview so staff don't have to
                // re-upload it. The actual file input stays empty; the
                // existing base64/URL is used directly in processCheckIn().
                if (idProof.photo) {
                    const frontPreview = document.getElementById('ciPhotoFrontPreview');
                    const frontPlaceholder = document.getElementById('ciPhotoFrontPlaceholder');
                    const frontBox = document.getElementById('ciPhotoFrontBox');
                    if (frontPreview) {
                        frontPreview.src = idProof.photo;
                        frontPreview.style.display = 'block';
                    }
                    if (frontPlaceholder) frontPlaceholder.style.display = 'none';
                    if (frontBox) {
                        frontBox.style.borderColor = '#198754';
                        frontBox.dataset.existingPhoto = idProof.photo;
                    }
                }
                if (idProof.photoBack) {
                    const backPreview = document.getElementById('ciPhotoBackPreview');
                    const backPlaceholder = document.getElementById('ciPhotoBackPlaceholder');
                    const backBox = document.getElementById('ciPhotoBackBox');
                    if (backPreview) {
                        backPreview.src = idProof.photoBack;
                        backPreview.style.display = 'block';
                    }
                    if (backPlaceholder) backPlaceholder.style.display = 'none';
                    if (backBox) backBox.dataset.existingPhoto = idProof.photoBack;
                }
            }
        }
    },

    processCheckIn() {
        if (_checkinSaving) return;
        _checkinSaving = true;

        const data = Utils.getFormData('checkInForm');
        if (!data.guestName || !data.mobile || !data.roomId) {
            Utils.showToast('Please fill guest name, mobile, and room', 'warning');
            _checkinSaving = false;
            return;
        }

        // ── ID Proof is mandatory — block check-in if missing ────
        if (!data.idProofType || !data.idNumber || data.idNumber.trim() === '') {
            Utils.showToast('ID proof is mandatory for check-in. Please enter ID type and number.', 'danger');
            // Highlight the ID fields
            const idTypeEl   = document.querySelector('[name="idProofType"]');
            const idNumberEl = document.querySelector('[name="idNumber"]');
            if (idTypeEl)   { idTypeEl.style.borderColor   = '#dc3545'; setTimeout(() => idTypeEl.style.borderColor   = '', 3000); }
            if (idNumberEl) { idNumberEl.style.borderColor = '#dc3545'; setTimeout(() => idNumberEl.style.borderColor = '', 3000); }
            _checkinSaving = false;
            return;
        }

        // ── ID photo is mandatory — block if not uploaded ─────────
        // Accept either a newly uploaded file OR an existing photo auto-filled
        // from the guest's online booking ID proof (stored in data-existingPhoto).
        const frontPhotoInput = document.getElementById('ciPhotoFront');
        const frontBox = document.getElementById('ciPhotoFrontBox');
        const hasNewFrontPhoto = frontPhotoInput && frontPhotoInput.files && frontPhotoInput.files.length > 0;
        const hasExistingFrontPhoto = frontBox && frontBox.dataset.existingPhoto;
        if (!hasNewFrontPhoto && !hasExistingFrontPhoto) {
            _checkinSaving = false;
            Utils.showToast('ID photo (front) is mandatory. Please upload before check-in.', 'danger');
            if (frontBox) {
                frontBox.style.borderColor = '#dc3545';
                frontBox.style.background  = '#fff5f5';
                frontBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => { frontBox.style.borderColor = '#C4943A'; frontBox.style.background = '#fdfaf5'; }, 3500);
            }
            return;
        }

        const room = DB.getById(DB.ROOMS, data.roomId);
        if (!room) {
            Utils.showToast('Selected room not found', 'danger');
            return;
        }

        // ── Block duplicate checkin for same room ─────────────────
        const activeForRoom = DB.query(DB.CHECKINS, c => c.roomId === data.roomId && c.status === 'Checked-In');
        if (activeForRoom.length > 0) {
            _checkinSaving = false;
            Utils.showToast(`Room ${room.roomNumber} already has an active check-in (${activeForRoom[0].guestName}). Please check them out first.`, 'danger');
            return;
        }

        // ── Block checkin to Maintenance/Out of Order rooms ───────
        if (['Maintenance','Out of Order'].includes(room.status)) {
            _checkinSaving = false;
            Utils.showToast(`Room ${room.roomNumber} is under ${room.status} and cannot be checked in.`, 'danger');
            return;
        }

        // ── Validate guest count vs room capacity ─────────────────
        const capacity = parseInt(room.capacity) || 20;
        const numGuests = parseInt(data.numGuests) || 1;
        if (numGuests > capacity) {
            Utils.showToast(
                `Room ${room.roomNumber} is a ${room.roomType} with max capacity of ${capacity}. You entered ${numGuests} guests — please reduce to ${capacity} or less.`,
                'danger'
            );
            const input = document.getElementById('numGuestsInput');
            if (input) { input.style.borderColor = '#dc3545'; setTimeout(() => input.style.borderColor = '', 3000); }
            return;
        }

        // Create check-in record
        const checkin = {
            guestName: data.guestName,
            mobile: data.mobile,
            roomId: data.roomId,
            roomNumber: room.roomNumber,
            bookingId: data.bookingId || null,
            checkInDate: Utils.today(),
            checkInTime: new Date().toISOString(),
            expectedCheckOut: data.expectedCheckOut || '',
            idProofType: data.idProofType || '',
            idNumber: data.idNumber || '',
            advance: parseFloat(data.advance) || 0,
            numGuests: parseInt(data.numGuests) || 1,
            status: 'Checked-In'
        };

        DB.add(DB.CHECKINS, checkin);
        DB.update(DB.ROOMS, data.roomId, { status: 'Occupied' });
        // Await the MySQL save BEFORE navigating away. Without await, the page
        // re-render triggers a pull that overwrites localStorage with empty
        // checkins from MySQL before the async save completes.
        DB._save('checkins', checkin);

        // Update booking status to Checked-In if linked
        // Use the bookingId from the select dropdown value (not form hidden field)
        const selectedBookingId = document.getElementById('checkInBooking')
            ? document.getElementById('checkInBooking').value
            : (data.bookingId || '');

        if (selectedBookingId) {
            // Update in localStorage immediately
            DB.update(DB.BOOKINGS, selectedBookingId, { status: 'Checked-In' });
            // Force a direct MySQL save so the status persists across auto-syncs
            DB._save('bookings', {
                ...DB.getById(DB.BOOKINGS, selectedBookingId),
                status: 'Checked-In'
            });
        }

        // Auto-create/update guest
        const existingGuest = DB.query(DB.GUESTS, g => g.mobile === data.mobile);
        let guestId = null;
        if (existingGuest.length === 0) {
            const newGuest = DB.add(DB.GUESTS, {
                name:          data.guestName,
                mobile:        data.mobile,
                email:         data.email        || '',
                nationality:   data.nationality  || 'Indian',
                address:       data.address      || '',
                idProofType:   data.idProofType,
                idProofNumber: data.idNumber
            });
            guestId = newGuest.id;
        } else {
            guestId = existingGuest[0].id;
            // Update missing fields if now provided
            const updates = {};
            if (data.email       && !existingGuest[0].email)      updates.email       = data.email;
            if (data.nationality && !existingGuest[0].nationality) updates.nationality = data.nationality;
            if (data.address     && !existingGuest[0].address)     updates.address     = data.address;
            if (Object.keys(updates).length > 0) DB.update(DB.GUESTS, guestId, updates);
        }

        // ── Auto-save ID proof WITH photo ─────────────────────────
        // If admin selected a booking whose ID photo was already uploaded during
        // online booking, use the existing photo (data-existingPhoto) so staff
        // don't have to re-upload it.
        if (guestId && data.idProofType && data.idNumber) {
            const frontFile = document.getElementById('ciPhotoFront')?.files[0];
            const backFile  = document.getElementById('ciPhotoBack')?.files[0];
            const existingFrontPhoto = document.getElementById('ciPhotoFrontBox')?.dataset.existingPhoto || null;
            const existingBackPhoto  = document.getElementById('ciPhotoBackBox')?.dataset.existingPhoto  || null;
            const saveProof = (frontDataUrl, backDataUrl) => {
                const existingProof = DB.query(DB.ID_PROOFS, p => p.guestId === guestId);
                if (existingProof.length === 0) {
                    DB.add(DB.ID_PROOFS, {
                        guestId, guestName: data.guestName, mobile: data.mobile,
                        idType: data.idProofType, idNumber: data.idNumber,
                        photo: frontDataUrl || null, photoBack: backDataUrl || null,
                        source: 'checkin'
                    });
                } else {
                    DB.update(DB.ID_PROOFS, existingProof[0].id, {
                        idType: data.idProofType, idNumber: data.idNumber,
                        photo: frontDataUrl || existingProof[0].photo,
                        photoBack: backDataUrl || existingProof[0].photoBack,
                    });
                }
            };
            if (frontFile) {
                // New file uploaded — read it
                const reader = new FileReader();
                reader.onload = e => {
                    if (backFile) {
                        const r2 = new FileReader();
                        r2.onload = e2 => saveProof(e.target.result, e2.target.result);
                        r2.readAsDataURL(backFile);
                    } else { saveProof(e.target.result, backFile ? null : existingBackPhoto); }
                };
                reader.readAsDataURL(frontFile);
            } else {
                // No new file — use existing photo from auto-fill
                saveProof(existingFrontPhoto, existingBackPhoto);
            }
        }

        _checkinSaving = false;
        Utils.showToast(`${data.guestName} checked into Room ${room.roomNumber}`, 'success');
        LodgeApp.navigate('checkin');
    },

    processCheckOut(checkinId) {
        const checkin = DB.getById(DB.CHECKINS, checkinId);
        if (!checkin) return;

        const room          = DB.getById(DB.ROOMS, checkin.roomId);
        const pricePerNight = room ? parseFloat(room.price) : 0;

        // ── Actual check-in datetime ─────────────────────────────
        // checkInTime is stored as ISO string when admin processed check-in
        const checkInDT  = new Date(checkin.checkInTime || checkin.checkInDate + 'T12:00:00');
        const checkOutDT = new Date(); // right now = actual checkout moment

        // ── Total stay in hours (exact) ──────────────────────────
        const totalMs    = checkOutDT - checkInDT;
        const totalHours = totalMs / (1000 * 60 * 60);

        // ── Full 24-hour blocks = base nights (minimum 1) ────────
        const baseNights = Math.max(1, Math.floor(totalHours / 24));

        // ── Remaining hours after last full 24-hr block ──────────
        const remainingHours = totalHours - (baseNights * 24);

        // ── Extra charge per billing rules ───────────────────────
        // 0 remaining    → no extra charge
        // 0 < r ≤ 6 hrs  → hourly (price ÷ 24 per hr, rounded up)
        // 6 < r ≤ 12 hrs → half-day (price ÷ 2)
        // r > 12 hrs     → full extra day (price × 1)
        let extraCharge = 0;
        let extraNote   = '';
        const fmt = (d) => d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

        if (remainingHours > 0.5) {  // 30-minute grace — no charge for trivial overrun
            if (remainingHours <= 6) {
                const hourlyRate    = pricePerNight / 24;
                const hoursToCharge = Math.ceil(remainingHours);
                extraCharge = hourlyRate * hoursToCharge;
                extraNote   = `${hoursToCharge} extra hour${hoursToCharge > 1 ? 's' : ''} beyond ${baseNights * 24} hrs · Hourly rate ₹${Math.round(hourlyRate)}/hr`;
            } else if (remainingHours <= 12) {
                extraCharge = pricePerNight / 2;
                extraNote   = `${remainingHours.toFixed(1)} extra hours (6–12 hr range) · Half-day charge`;
            } else {
                extraCharge = pricePerNight;
                extraNote   = `${remainingHours.toFixed(1)} extra hours (>12 hr range) · Full extra day charge`;
            }
        }

        // ── Free deadline (for info note) ─────────────────────────
        const freeDeadline = new Date(checkInDT.getTime() + baseNights * 24 * 60 * 60 * 1000);

        const roomCharges = baseNights * pricePerNight;

        // Room service charges
        const serviceOrders  = DB.query(DB.ROOM_SERVICE, o => o.checkinId === checkinId || o.roomId === checkin.roomId);
        const serviceCharges = serviceOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0);

        // Amenity charges
        const guestServices  = DB.query(DB.GUEST_SERVICES, gs => gs.checkinId === checkinId);
        const amenityCharges = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);

        // Advance — from checkin or linked booking
        let advance = parseFloat(checkin.advance) || 0;
        if (advance === 0 && checkin.bookingId) {
            const linked = DB.getById(DB.BOOKINGS, checkin.bookingId);
            if (linked && parseFloat(linked.advance) > 0) {
                advance = parseFloat(linked.advance);
                DB.update(DB.CHECKINS, checkinId, { advance });
            }
        }

        const subtotal = roomCharges + extraCharge + serviceCharges + amenityCharges;
        const tax      = subtotal * 0.12;
        const total    = subtotal + tax;
        const balance  = total - advance;

        // fmtDT removed — use fmt instead // (d) => d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

        const html = `
            <div class="bill-container">
                <div class="bill-header">
                    <h3>Karavali Lodge</h3>
                    <p class="text-muted mb-0">Lodge Check-Out Summary</p>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong>Guest:</strong> ${Utils.escapeHtml(checkin.guestName)}<br>
                        <strong>Mobile:</strong> ${Utils.escapeHtml(checkin.mobile)}<br>
                        <strong>Room:</strong> ${room ? Utils.escapeHtml(room.roomNumber) + ' (' + Utils.escapeHtml(room.roomType) + ')' : '-'}
                    </div>
                    <div class="col-6 text-end">
                        <strong>Check-In:</strong> ${Utils.formatDate(checkin.checkInDate)}
                        <small class="text-muted d-block">${fmt(checkInDT)}</small>
                        <strong>Check-Out:</strong> ${Utils.formatDate(Utils.today())}
                        <small class="text-muted d-block">${fmt(checkOutDT)}</small>
                        <strong>Duration:</strong> ${baseNights} night(s)
                    </div>
                </div>
                <table class="table bill-table">
                    <tr>
                        <td>Room Charges (${baseNights} night${baseNights>1?'s':''} × ${Utils.formatCurrency(pricePerNight)})</td>
                        <td class="text-end">${Utils.formatCurrency(roomCharges)}</td>
                    </tr>
                    ${extraCharge > 0 ? `
                    <tr style="background:#fff9ec">
                        <td>
                            <span style="color:#e67e22;font-weight:600">⚡ Extra Time Charge</span><br>
                            <small style="color:#888">${extraNote}</small>
                        </td>
                        <td class="text-end" style="color:#e67e22;font-weight:600">${Utils.formatCurrency(extraCharge)}</td>
                    </tr>` : `
                    <tr style="background:#f0fff4">
                        <td colspan="2">
                            <small style="color:#28a745">✓ Checked out on time — no extra charge</small>
                        </td>
                    </tr>`}
                    <tr><td>Restaurant / Room Service</td><td class="text-end">${Utils.formatCurrency(serviceCharges)}</td></tr>
                    <tr><td>Amenity / Extra Services</td><td class="text-end">${Utils.formatCurrency(amenityCharges)}</td></tr>
                    <tr><td><strong>Subtotal</strong></td><td class="text-end"><strong>${Utils.formatCurrency(subtotal)}</strong></td></tr>
                    <tr><td>GST (12%)</td><td class="text-end">${Utils.formatCurrency(tax)}</td></tr>
                    <tr class="bill-total"><td><strong>Total Amount</strong></td><td class="text-end"><strong>${Utils.formatCurrency(total)}</strong></td></tr>
                    <tr><td>Advance Paid</td><td class="text-end text-success">- ${Utils.formatCurrency(advance)}</td></tr>
                    <tr><td><strong>Balance Due</strong></td><td class="text-end"><strong class="text-danger">${Utils.formatCurrency(balance)}</strong></td></tr>
                </table>
                <div style="background:#faf7f3;border-radius:8px;padding:10px 14px;margin-top:8px;font-size:0.78rem;color:#888">
                    <i class="bi bi-info-circle me-1"></i>
                    Free checkout until <strong>${fmt(freeDeadline)}</strong>
                    (${baseNights * 24} hrs from check-in).
                    Extra: 1–6 hrs = hourly · 6–12 hrs = half-day · 12+ hrs = full day.
                </div>
            </div>
        `;

        document.getElementById('checkOutContent').innerHTML = html;
        document.getElementById('confirmCheckOutBtn').onclick = () => {
            this.confirmCheckOut(checkinId, {
                roomCharges,
                extraCharge,
                extraNote,
                serviceCharges, amenityCharges, tax, total, balance,
                days: baseNights, advance
            });
        };
        new bootstrap.Modal(document.getElementById('checkOutModal')).show();
    },

    previewPhoto(input, previewId, placeholderId, boxId) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { Utils.showToast('Photo must be under 5MB', 'warning'); input.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            const box = document.getElementById(boxId);
            if (preview) { preview.src = e.target.result; preview.style.display = 'block'; }
            if (placeholder) placeholder.style.display = 'none';
            if (box) { box.style.borderStyle = 'solid'; box.style.borderColor = '#28a745'; box.style.background = '#f0fff4'; }
        };
        reader.readAsDataURL(file);
    },

    confirmCheckOut(checkinId, charges) {
        const checkin = DB.getById(DB.CHECKINS, checkinId);
        if (!checkin) return;

        // Update check-in record
        DB.update(DB.CHECKINS, checkinId, {
            status: 'Checked-Out',
            checkOutDate: Utils.today(),
            checkOutTime: new Date().toISOString()
        });

        // Mark room for cleaning — only create housekeeping task if none exists for this room already
        DB.update(DB.ROOMS, checkin.roomId, { status: 'Cleaning' });
        const existingHKTask = DB.query(DB.HOUSEKEEPING, h =>
            h.roomId === checkin.roomId &&
            (h.status === 'Dirty' || h.status === 'Cleaning')
        );
        if (existingHKTask.length === 0) {
            DB.add(DB.HOUSEKEEPING, { roomId: checkin.roomId, status: 'Dirty', assignedDate: Utils.today() });
        } else {
            // Update the existing task back to Dirty so it can be re-assigned
            DB.update(DB.HOUSEKEEPING, existingHKTask[0].id, {
                status: 'Dirty',
                assignedTo: '',
                assignedDate: Utils.today(),
                completedAt: null
            });
        }

        // Update linked booking
        if (checkin.bookingId) {
            DB.update(DB.BOOKINGS, checkin.bookingId, { status: 'Completed' });
        }

        // Generate bill — skip if one already exists for this checkin
        const existingBill = DB.query(DB.BILLS, b => b.checkinId === checkinId)[0];
        if (existingBill) {
            // Update the existing bill instead of creating a duplicate
            DB.update(DB.BILLS, existingBill.id, {
                checkOut: Utils.today(),
                nights: charges.days,
                roomCharges: charges.roomCharges,
                extraCharge: charges.extraCharge || 0,
                extraNote:   charges.extraNote   || '',
                serviceCharges: charges.serviceCharges,
                amenityCharges: charges.amenityCharges,
                tax: charges.tax,
                totalAmount: charges.total,
                advance: charges.advance || checkin.advance || 0,
                balance: charges.balance,
                status: charges.balance <= 0 ? 'Paid' : 'Unpaid'
            });
        } else {
        const guest = DB.query(DB.GUESTS, g => g.mobile === checkin.mobile)[0];
        DB.add(DB.BILLS, {
            billNo: Utils.generateBillNo(),
            checkinId: checkinId,
            guestId: guest ? guest.id : null,
            guestName: checkin.guestName,
            mobile: checkin.mobile,
            roomId: checkin.roomId,
            checkIn: checkin.checkInDate,
            checkOut: Utils.today(),
            nights: charges.days,
            roomCharges: charges.roomCharges,
            extraCharge: charges.extraCharge || 0,
            extraNote:   charges.extraNote   || '',
            serviceCharges: charges.serviceCharges,
            amenityCharges: charges.amenityCharges,
            tax: charges.tax,
            totalAmount: charges.total,
            advance: charges.advance || checkin.advance || 0,
            balance: charges.balance,
            status: charges.balance <= 0 ? 'Paid' : 'Unpaid'
        });
        } // end else (no existing bill)

        bootstrap.Modal.getInstance(document.getElementById('checkOutModal')).hide();
        Utils.showToast(`${checkin.guestName} checked out successfully. Bill generated.`, 'success');
        LodgeApp.navigate('checkin');
    }
};
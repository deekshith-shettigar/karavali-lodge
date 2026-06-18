/* ==========================================
   ID Verification Module
   ========================================== */
const IDVerifyModule = {
    _editingId: null,
    _filter: 'all',

    render() {
        const guests = DB.getAll(DB.GUESTS);
        const idProofs = DB.getAll(DB.ID_PROOFS);
        const verifiedIds = new Set(idProofs.map(ip => ip.guestId));
        const verifiedCount = guests.filter(g => verifiedIds.has(g.id)).length;
        const unverifiedCount = guests.length - verifiedCount;

        // Filter guests for the table based on current filter
        let filteredProofs = idProofs;
        let unverifiedGuests = [];
        if (this._filter === 'unverified') {
            unverifiedGuests = guests.filter(g => !verifiedIds.has(g.id));
            filteredProofs = [];
        } else if (this._filter === 'verified') {
            // show only proofs (already verified)
        }

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card clickable" onclick="IDVerifyModule.setFilter('all')">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${idProofs.length}</div>
                                <div class="stat-label">ID Proofs on File</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-badge"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card clickable" onclick="IDVerifyModule.setFilter('verified')">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${verifiedCount}</div>
                                <div class="stat-label">Verified Guests</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-shield-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card clickable" onclick="IDVerifyModule.setFilter('unverified')">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${unverifiedCount}</div>
                                <div class="stat-label">Unverified Guests</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-upload me-2"></i>${this._editingId ? 'Edit ID Proof' : 'Upload ID Proof'}</span>
                            ${this._editingId ? `<button class="btn btn-sm btn-outline-light" onclick="IDVerifyModule.cancelEdit()"><i class="bi bi-x-lg me-1"></i>Cancel</button>` : ''}
                        </div>
                        <div class="card-body">
                            <form id="idVerifyForm">
                                <input type="hidden" name="editId" id="idEditId" value="${this._editingId || ''}">
                                <div class="mb-3">
                                    <label class="form-label">Select Guest *</label>
                                    <select class="form-select" name="guestId" id="idGuestSelect" required ${this._editingId ? 'disabled' : ''}>
                                        <option value="">Choose Guest</option>
                                        ${guests.map(g => `<option value="${g.id}">${Utils.escapeHtml(g.name)} - ${Utils.escapeHtml(g.mobile || '')}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ID Proof Type *</label>
                                    <select class="form-select" name="idType" id="idTypeSelect" required
                                            onchange="IDVerifyModule.onIdTypeChange()">
                                        <option value="">Select Type</option>
                                        ${Utils.idProofTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ID Number *</label>
                                    <input type="text" class="form-control" name="idNumber" id="idNumberInput"
                                           required maxlength="30" placeholder="Enter ID number"
                                           oninput="IDVerifyModule.onIdNumberInput(this)">
                                    <div id="idFormatHint" style="margin-top:6px;font-size:0.8rem;color:#8B7355;display:none">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <span id="idFormatHintText"></span>
                                    </div>
                                    <div id="idValidationMsg" style="margin-top:4px;font-size:0.8rem;display:none"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload ID Photo (Front)</label>
                                    <input type="file" class="form-control" id="idPhotoUpload" accept="image/*">
                                    <small class="text-muted">Accepted: JPG, PNG (max 2MB)</small>
                                </div>
                                <div class="mb-3" id="idPhotoPreviewContainer" style="display:none">
                                    <label class="form-label">Front Preview</label><br>
                                    <img id="idPhotoPreview" class="id-preview" alt="ID Preview">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="IDVerifyModule.clearPhoto('front')"><i class="bi bi-x"></i> Remove</button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload ID Photo (Back) <small class="text-muted">- optional</small></label>
                                    <input type="file" class="form-control" id="idPhotoUploadBack" accept="image/*">
                                </div>
                                <div class="mb-3" id="idPhotoPreviewBackContainer" style="display:none">
                                    <label class="form-label">Back Preview</label><br>
                                    <img id="idPhotoPreviewBack" class="id-preview" alt="ID Back Preview">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="IDVerifyModule.clearPhoto('back')"><i class="bi bi-x"></i> Remove</button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" class="form-control" name="notes" id="idNotesInput" maxlength="200" placeholder="Any additional notes...">
                                </div>
                                <button type="button" class="btn btn-accent w-100" onclick="IDVerifyModule.saveIdProof()">
                                    <i class="bi bi-shield-check me-2"></i>${this._editingId ? 'Update ID Proof' : 'Save & Verify'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-list me-2"></i>
                                ${this._filter === 'unverified' ? 'Unverified Guests' : this._filter === 'verified' ? 'Verified ID Records' : 'All ID Proof Records'}
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control form-control-sm" id="idSearchInput" placeholder="Search guest or ID..." style="width:180px" oninput="IDVerifyModule.filterTable()">
                                <select class="form-select form-select-sm" style="width:auto" id="idFilterSelect" onchange="IDVerifyModule.setFilter(this.value)">
                                    <option value="all" ${this._filter === 'all' ? 'selected' : ''}>All</option>
                                    <option value="verified" ${this._filter === 'verified' ? 'selected' : ''}>Verified</option>
                                    <option value="unverified" ${this._filter === 'unverified' ? 'selected' : ''}>Unverified</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table" id="idProofTable">
                                    <thead>
                                        <tr>
                                            <th>Guest</th>
                                            <th>ID Type</th>
                                            <th>ID Number</th>
                                            <th>Photo</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${this._filter === 'unverified' ? (
                                            unverifiedGuests.length === 0 ? '<tr><td colspan="6" class="text-center py-4 text-muted">All guests are verified!</td></tr>' :
                                            unverifiedGuests.map(g => `
                                            <tr>
                                                <td><strong>${Utils.escapeHtml(g.name)}</strong><br><small class="text-muted">${Utils.escapeHtml(g.mobile || '')}</small></td>
                                                <td colspan="3"><span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>No ID uploaded</span></td>
                                                <td>${Utils.formatDate(g.createdAt)}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="IDVerifyModule.uploadForGuest('${g.id}')" title="Upload ID">
                                                        <i class="bi bi-upload me-1"></i>Upload
                                                    </button>
                                                </td>
                                            </tr>`).join('')
                                        ) : (
                                            filteredProofs.length === 0 ? '<tr><td colspan="6" class="text-center py-4 text-muted">No ID proofs uploaded</td></tr>' :
                                            filteredProofs.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).map(ip => {
                                                const guest = DB.getById(DB.GUESTS, ip.guestId);
                                                const hasPhotos = ip.photo || ip.photoBack;
                                                const photoCount = (ip.photo ? 1 : 0) + (ip.photoBack ? 1 : 0);
                                                return `
                                                <tr>
                                                    <td><strong>${guest ? Utils.escapeHtml(guest.name) : 'Unknown'}</strong><br><small class="text-muted">${guest ? Utils.escapeHtml(guest.mobile || '') : ''}</small></td>
                                                    <td><span class="badge bg-secondary">${Utils.escapeHtml(ip.idType)}</span></td>
                                                    <td><code>${Utils.escapeHtml(ip.idNumber)}</code></td>
                                                    <td>${hasPhotos
                                                        ? `<img src="${ip.photo || ip.photoBack}" alt="ID"
                                                               onclick="IDVerifyModule.viewDetail('${ip.id}')"
                                                               style="width:52px;height:38px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid #28a745;"
                                                               title="Click to view full details">`
                                                        : '<span class="badge bg-secondary">No photo</span>'
                                                    }</td>
                                                    <td>${Utils.formatDate(ip.createdAt)}</td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-info" onclick="IDVerifyModule.viewDetail('${ip.id}')" title="View Details"><i class="bi bi-eye"></i></button>
                                                            <button class="btn btn-outline-primary" onclick="IDVerifyModule.editProof('${ip.id}')" title="Edit"><i class="bi bi-pencil"></i></button>
                                                            <button class="btn btn-outline-danger" onclick="IDVerifyModule.deleteProof('${ip.id}')" title="Delete"><i class="bi bi-trash"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>`;
                                            }).join('')
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ID Detail View Modal -->
            <div class="modal fade" id="idDetailModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background:var(--primary);color:#fff">
                            <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>ID Verification Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="idDetailContent"></div>
                    </div>
                </div>
            </div>

            <!-- Photo Zoom Modal -->
            <div class="modal fade" id="idPhotoModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="idPhotoModalTitle">ID Proof Photo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center p-2">
                            <img id="idPhotoModalImg" style="max-width:100%;max-height:70vh;border-radius:8px;object-fit:contain" alt="ID Proof">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="IDVerifyModule.downloadPhoto()"><i class="bi bi-download me-1"></i>Download</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    init() {
        // Attach file input listeners for front and back photos
        this._attachFileListener('idPhotoUpload', 'idPhotoPreview', 'idPhotoPreviewContainer');
        this._attachFileListener('idPhotoUploadBack', 'idPhotoPreviewBack', 'idPhotoPreviewBackContainer');

        // If editing, populate form fields
        if (this._editingId) {
            const proof = DB.getById(DB.ID_PROOFS, this._editingId);
            if (proof) {
                const sel = document.getElementById('idGuestSelect');
                if (sel) sel.value = proof.guestId;
                const typeSel = document.getElementById('idTypeSelect');
                if (typeSel) typeSel.value = proof.idType;
                const numInput = document.getElementById('idNumberInput');
                if (numInput) numInput.value = proof.idNumber;
                // Show format hint for the selected type
                setTimeout(() => IDVerifyModule.onIdTypeChange(), 50);
                const notesInput = document.getElementById('idNotesInput');
                if (notesInput) notesInput.value = proof.notes || '';
                // Show existing photos
                if (proof.photo) {
                    document.getElementById('idPhotoPreview').src = proof.photo;
                    document.getElementById('idPhotoPreviewContainer').style.display = 'block';
                }
                if (proof.photoBack) {
                    document.getElementById('idPhotoPreviewBack').src = proof.photoBack;
                    document.getElementById('idPhotoPreviewBackContainer').style.display = 'block';
                }
            }
        }
    },

    _attachFileListener(inputId, previewId, containerId) {
        const fileInput = document.getElementById(inputId);
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                if (file.size > 2 * 1024 * 1024) {
                    Utils.showToast('File must be less than 2MB', 'warning');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    // ── Compress image using canvas before storing ────────
                    // This reduces a 2MB photo to ~80-120KB so MySQL can store it
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        // Max dimension 800px (enough for ID verification)
                        const MAX = 800;
                        let w = img.width, h = img.height;
                        if (w > MAX || h > MAX) {
                            if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                            else       { w = Math.round(w * MAX / h); h = MAX; }
                        }
                        canvas.width  = w;
                        canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        // Quality 0.75 gives good balance of size vs clarity
                        const compressed = canvas.toDataURL('image/jpeg', 0.75);
                        document.getElementById(previewId).src = compressed;
                        document.getElementById(containerId).style.display = 'block';
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    },

    clearPhoto(side) {
        if (side === 'front') {
            document.getElementById('idPhotoUpload').value = '';
            document.getElementById('idPhotoPreview').src = '';
            document.getElementById('idPhotoPreviewContainer').style.display = 'none';
        } else {
            document.getElementById('idPhotoUploadBack').value = '';
            document.getElementById('idPhotoPreviewBack').src = '';
            document.getElementById('idPhotoPreviewBackContainer').style.display = 'none';
        }
    },

    setFilter(f) {
        this._filter = f;
        LodgeApp.navigate('idverify');
    },

    filterTable() {
        const term = (document.getElementById('idSearchInput').value || '').toLowerCase();
        const rows = document.querySelectorAll('#idProofTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    },

    uploadForGuest(guestId) {
        this._editingId = null;
        this._filter = 'all';
        LodgeApp.navigate('idverify');
        setTimeout(() => {
            const sel = document.getElementById('idGuestSelect');
            if (sel) sel.value = guestId;
            sel.focus();
        }, 100);
    },

    // ── ID format rules ───────────────────────────────────────────
    _idRules: {
        'Aadhaar':         { regex: /^\d{12}$|^\d{4}\s\d{4}\s\d{4}$/, hint: 'Format: 12 digits  e.g. 1234 5678 9012', placeholder: 'e.g. 123456789012', maxlen: 14 },
        'Passport':        { regex: /^[A-Z]\d{7}$/i,                   hint: 'Format: 1 letter + 7 digits  e.g. A1234567', placeholder: 'e.g. A1234567', maxlen: 8 },
        'Driving License': { regex: /^[A-Z]{2}\d{2}\s?\d{4}\s?\d{7}$/i, hint: 'Format: State code + 13 digits  e.g. KA0120210012345', placeholder: 'e.g. KA0120210012345', maxlen: 16 },
        'Voter ID':        { regex: /^[A-Z]{3}\d{7}$/i,                hint: 'Format: 3 letters + 7 digits  e.g. ABC1234567', placeholder: 'e.g. ABC1234567', maxlen: 10 },
        'PAN Card':        { regex: /^[A-Z]{5}\d{4}[A-Z]$/i,          hint: 'Format: 5 letters + 4 digits + 1 letter  e.g. ABCDE1234F', placeholder: 'e.g. ABCDE1234F', maxlen: 10 },
    },

    // ── Called when ID type dropdown changes ──────────────────────
    onIdTypeChange() {
        const type  = document.getElementById('idTypeSelect')?.value;
        const input = document.getElementById('idNumberInput');
        const hint  = document.getElementById('idFormatHint');
        const hintText = document.getElementById('idFormatHintText');
        const msg   = document.getElementById('idValidationMsg');

        if (!type || !this._idRules[type]) {
            if (hint) hint.style.display = 'none';
            if (input) { input.placeholder = 'Enter ID number'; input.maxLength = 30; }
            return;
        }

        const rule = this._idRules[type];
        if (input) {
            input.placeholder = rule.placeholder;
            input.maxLength   = rule.maxlen;
            input.value       = '';    // clear previous ID when type changes
            input.className   = input.className.replace(/ ?is-valid| ?is-invalid/g, '');
        }
        if (hint)     { hint.style.display = 'block'; }
        if (hintText) { hintText.textContent = rule.hint; }
        if (msg)      { msg.style.display = 'none'; }
    },

    // ── Live validation feedback as user types ────────────────────
    onIdNumberInput(input) {
        const type = document.getElementById('idTypeSelect')?.value;
        const msg  = document.getElementById('idValidationMsg');
        if (!type || !this._idRules[type] || !msg) return;

        const val  = input.value.trim().toUpperCase();
        const rule = this._idRules[type];

        // Remove old classes
        input.classList.remove('is-valid', 'is-invalid');
        msg.style.display = 'none';

        if (!val) return;

        if (rule.regex.test(val)) {
            input.classList.add('is-valid');
            msg.style.display = 'block';
            msg.innerHTML = '<span style="color:#27ae60"><i class="bi bi-check-circle-fill me-1"></i>Valid ' + type + ' number</span>';
        } else {
            // Only show invalid once user has typed enough characters
            if (val.length >= Math.floor(rule.maxlen * 0.6)) {
                input.classList.add('is-invalid');
                msg.style.display = 'block';
                msg.innerHTML = '<span style="color:#dc3545"><i class="bi bi-x-circle-fill me-1"></i>Invalid format — ' + rule.hint + '</span>';
            }
        }
    },

    // ── Validate ID number format ─────────────────────────────────
    _validateIdNumber(type, number) {
        const rule = this._idRules[type];
        if (!rule) return { valid: true };   // unknown type — allow
        const clean = number.trim().toUpperCase();
        if (!rule.regex.test(clean)) {
            return { valid: false, message: `Invalid ${type} number. ${rule.hint}` };
        }
        return { valid: true };
    },

    saveIdProof() {
        const data = Utils.getFormData('idVerifyForm');
        const guestId = this._editingId ? (DB.getById(DB.ID_PROOFS, this._editingId) || {}).guestId : data.guestId;
        if (!guestId || !data.idType || !data.idNumber) {
            Utils.showToast('Please fill all required fields', 'warning');
            return;
        }

        // ── Validate ID number format ─────────────────────────────
        const validation = this._validateIdNumber(data.idType, data.idNumber);
        if (!validation.valid) {
            Utils.showToast(validation.message, 'danger');
            const input = document.getElementById('idNumberInput');
            if (input) {
                input.classList.add('is-invalid');
                input.focus();
                const msg = document.getElementById('idValidationMsg');
                if (msg) {
                    msg.style.display = 'block';
                    msg.innerHTML = `<span style="color:#dc3545"><i class="bi bi-x-circle-fill me-1"></i>${validation.message}</span>`;
                }
            }
            return;
        }

        const photoPreview = document.getElementById('idPhotoPreview');
        const photo = photoPreview && photoPreview.src && photoPreview.src.startsWith('data:') ? photoPreview.src : null;
        const photoBackPreview = document.getElementById('idPhotoPreviewBack');
        const photoBack = photoBackPreview && photoBackPreview.src && photoBackPreview.src.startsWith('data:') ? photoBackPreview.src : null;

        const record = {
            guestId: guestId,
            idType: data.idType,
            idNumber: data.idNumber,
            photo: photo,
            photoBack: photoBack,
            notes: data.notes
        };

        if (this._editingId) {
            // Keep existing photos if not replaced
            const existing = DB.getById(DB.ID_PROOFS, this._editingId);
            if (!record.photo && existing.photo) record.photo = existing.photo;
            if (!record.photoBack && existing.photoBack) record.photoBack = existing.photoBack;
            DB.update(DB.ID_PROOFS, this._editingId, record);
            Utils.showToast('ID proof updated', 'success');
        } else {
            DB.add(DB.ID_PROOFS, record);
            Utils.showToast('ID proof saved and verified', 'success');
        }

        // Update guest record
        DB.update(DB.GUESTS, guestId, {
            idProofType: data.idType,
            idProofNumber: data.idNumber
        });

        this._editingId = null;
        LodgeApp.navigate('idverify');
    },

    editProof(id) {
        this._editingId = id;
        LodgeApp.navigate('idverify');
    },

    cancelEdit() {
        this._editingId = null;
        LodgeApp.navigate('idverify');
    },

    viewDetail(id) {
        const proof = DB.getById(DB.ID_PROOFS, id);
        if (!proof) return;
        const guest = DB.getById(DB.GUESTS, proof.guestId);
        const bookings = guest ? DB.query(DB.BOOKINGS, b => b.mobile === guest.mobile) : [];
        const stays = bookings.length;

        document.getElementById('idDetailContent').innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-accent mb-3" style="border-bottom:2px solid var(--accent);padding-bottom:6px">
                        <i class="bi bi-person me-1"></i>Guest Information
                    </h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" style="width:120px">Name</td><td><strong>${guest ? Utils.escapeHtml(guest.name) : 'Unknown'}</strong></td></tr>
                        <tr><td class="text-muted">Mobile</td><td>${guest ? Utils.escapeHtml(guest.mobile || '-') : '-'}</td></tr>
                        <tr><td class="text-muted">Email</td><td>${guest ? Utils.escapeHtml(guest.email || '-') : '-'}</td></tr>
                        <tr><td class="text-muted">Address</td><td>${guest ? Utils.escapeHtml(guest.address || '-') : '-'}</td></tr>
                        <tr><td class="text-muted">Nationality</td><td>${guest ? Utils.escapeHtml(guest.nationality || 'Indian') : '-'}</td></tr>
                        <tr><td class="text-muted">Total Stays</td><td><span class="badge bg-primary">${stays}</span></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-accent mb-3" style="border-bottom:2px solid var(--accent);padding-bottom:6px">
                        <i class="bi bi-shield-check me-1"></i>ID Verification
                    </h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" style="width:120px">ID Type</td><td><span class="badge bg-secondary">${Utils.escapeHtml(proof.idType)}</span></td></tr>
                        <tr><td class="text-muted">ID Number</td><td><code style="font-size:1em">${Utils.escapeHtml(proof.idNumber)}</code></td></tr>
                        <tr><td class="text-muted">Verified On</td><td>${Utils.formatDate(proof.createdAt)}</td></tr>
                        ${proof.updatedAt ? `<tr><td class="text-muted">Last Updated</td><td>${Utils.formatDate(proof.updatedAt)}</td></tr>` : ''}
                        <tr><td class="text-muted">Notes</td><td>${proof.notes ? Utils.escapeHtml(proof.notes) : '<em class="text-muted">None</em>'}</td></tr>
                        <tr><td class="text-muted">Status</td><td><span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span></td></tr>
                    </table>
                </div>
            </div>
            ${proof.photo || proof.photoBack ? `
            <h6 class="text-accent mt-4 mb-3" style="border-bottom:2px solid var(--accent);padding-bottom:6px">
                <i class="bi bi-images me-1"></i>ID Photos
            </h6>
            <div class="row g-3">
                ${proof.photo ? `
                <div class="col-md-6">
                    <div class="text-center">
                        <small class="text-muted d-block mb-1">Front</small>
                        <img src="${proof.photo}" class="id-detail-photo" alt="ID Front" onclick="IDVerifyModule.viewPhoto('${id}','front')" style="cursor:pointer;max-width:100%;max-height:250px;border-radius:8px;border:2px solid #eee">
                    </div>
                </div>` : ''}
                ${proof.photoBack ? `
                <div class="col-md-6">
                    <div class="text-center">
                        <small class="text-muted d-block mb-1">Back</small>
                        <img src="${proof.photoBack}" class="id-detail-photo" alt="ID Back" onclick="IDVerifyModule.viewPhoto('${id}','back')" style="cursor:pointer;max-width:100%;max-height:250px;border-radius:8px;border:2px solid #eee">
                    </div>
                </div>` : ''}
            </div>` : '<p class="text-muted mt-3"><i class="bi bi-info-circle me-1"></i>No photos uploaded for this ID proof.</p>'}
        `;
        new bootstrap.Modal(document.getElementById('idDetailModal')).show();
    },

    viewPhoto(id, side) {
        const proof = DB.getById(DB.ID_PROOFS, id);
        if (!proof) return;
        const photo = side === 'back' ? proof.photoBack : proof.photo;
        if (!photo) return;
        const guest = DB.getById(DB.GUESTS, proof.guestId);
        document.getElementById('idPhotoModalImg').src = photo;
        document.getElementById('idPhotoModalTitle').textContent = `${guest ? guest.name : 'Unknown'} — ${proof.idType} (${side === 'back' ? 'Back' : 'Front'})`;
        this._currentPhotoData = photo;
        this._currentPhotoName = `${(guest ? guest.name : 'guest').replace(/\s+/g, '_')}_${proof.idType}_${side || 'front'}`;

        // Close detail modal first if open
        const detailModal = bootstrap.Modal.getInstance(document.getElementById('idDetailModal'));
        if (detailModal) detailModal.hide();

        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('idPhotoModal')).show();
        }, 300);
    },

    downloadPhoto() {
        if (!this._currentPhotoData) return;
        const link = document.createElement('a');
        link.href = this._currentPhotoData;
        link.download = (this._currentPhotoName || 'id_proof') + '.png';
        link.click();
    },

    deleteProof(id) {
        if (!Utils.confirmAction('Delete this ID proof? This cannot be undone.')) return;
        DB.remove(DB.ID_PROOFS, id);
        Utils.showToast('ID proof deleted', 'info');
        LodgeApp.navigate('idverify');
    }
};
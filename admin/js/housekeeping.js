/* ==========================================
   Housekeeping Management Module
   (with Staff Management panel)
   ==========================================
   CHANGES:
   · Staff data now syncs to MySQL via DB._save() / DB.add() / DB.update() / DB.remove()
     using the 'hk_staff' collection — persists across browsers/devices/cache clears
   · Added Edit button in staff Action column (inline edit with save/cancel)
   ========================================== */

const HousekeepingModule = {

    // ── Staff CRUD — uses DB layer (localStorage + MySQL sync) ────
    getStaff() {
        return DB.getAll('hk_staff');
    },

    addStaff(name, category) {
        const trimmed = name.trim();
        if (!trimmed) return false;
        // Prevent exact duplicates
        const existing = this.getStaff();
        if (existing.find(s => s.name.toLowerCase() === trimmed.toLowerCase() && s.category === category)) return false;
        DB.add('hk_staff', { name: trimmed, category });
        return true;
    },

    updateStaff(id, name, category) {
        const trimmed = name.trim();
        if (!trimmed) return false;
        DB.update('hk_staff', id, { name: trimmed, category });
        return true;
    },

    removeStaff(id) {
        DB.remove('hk_staff', id);
    },

    // ── Build the staff-by-category dropdown HTML ─────────────────
    staffDropdownOptions(categoryFilter) {
        const staff = this.getStaff().filter(s =>
            !categoryFilter || s.category === categoryFilter
        );
        if (staff.length === 0) {
            return `<option value="">-- No ${categoryFilter || ''} staff added yet --</option>`;
        }
        const grouped = {};
        staff.forEach(s => {
            if (!grouped[s.category]) grouped[s.category] = [];
            grouped[s.category].push(s);
        });
        let html = '<option value="">Select Staff</option>';
        Object.keys(grouped).forEach(cat => {
            html += `<optgroup label="${Utils.escapeHtml(cat)}">`;
            grouped[cat].forEach(s => {
                html += `<option value="${Utils.escapeHtml(s.name)}">${Utils.escapeHtml(s.name)}</option>`;
            });
            html += `</optgroup>`;
        });
        return html;
    },

    // ── Staff list rows ───────────────────────────────────────────
    staffTableRows() {
        const list = this.getStaff();
        if (list.length === 0) {
            return `<tr><td colspan="4" class="text-center py-3 text-muted">
                        <i class="bi bi-people d-block mb-1" style="font-size:1.4rem"></i>
                        No staff added yet
                    </td></tr>`;
        }
        return list.map(s => `
            <tr id="staff-row-${s.id}">
                <td id="staff-name-cell-${s.id}"><strong>${Utils.escapeHtml(s.name)}</strong></td>
                <td id="staff-cat-cell-${s.id}">
                    <span class="badge ${s.category === 'Cleaning' ? 'badge-cleaning' : 'badge-maintenance'}">
                        <i class="bi ${s.category === 'Cleaning' ? 'bi-brush' : 'bi-wrench'} me-1"></i>
                        ${Utils.escapeHtml(s.category)}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary"
                                onclick="HousekeepingModule.startEditStaff('${s.id}')"
                                title="Edit staff member" id="edit-btn-${s.id}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger"
                                onclick="HousekeepingModule.deleteStaff('${s.id}')"
                                title="Remove staff">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    // ── Inline edit: replace cells with inputs ────────────────────
    startEditStaff(id) {
        const staff = this.getStaff().find(s => s.id === id);
        if (!staff) return;

        const nameCell = document.getElementById(`staff-name-cell-${id}`);
        const catCell  = document.getElementById(`staff-cat-cell-${id}`);
        const editBtn  = document.getElementById(`edit-btn-${id}`);
        if (!nameCell || !catCell) return;

        // Replace name cell with input
        nameCell.innerHTML = `
            <input type="text" class="form-control form-control-sm"
                   id="edit-name-${id}" value="${Utils.escapeHtml(staff.name)}"
                   maxlength="60" style="min-width:120px"
                   onkeydown="if(event.key==='Enter') HousekeepingModule.saveEditStaff('${id}');
                              if(event.key==='Escape') HousekeepingModule.cancelEditStaff('${id}')">
        `;

        // Replace category cell with select
        catCell.innerHTML = `
            <select class="form-select form-select-sm" id="edit-cat-${id}" style="min-width:130px">
                <option value="Cleaning"    ${staff.category === 'Cleaning'    ? 'selected' : ''}>🧹 Cleaning</option>
                <option value="Maintenance" ${staff.category === 'Maintenance' ? 'selected' : ''}>🔧 Maintenance</option>
            </select>
        `;

        // Swap edit button to Save + Cancel
        const btnGroup = editBtn.parentElement;
        btnGroup.innerHTML = `
            <button class="btn btn-sm btn-success" onclick="HousekeepingModule.saveEditStaff('${id}')" title="Save">
                <i class="bi bi-check-lg"></i>
            </button>
            <button class="btn btn-sm btn-secondary" onclick="HousekeepingModule.cancelEditStaff('${id}')" title="Cancel">
                <i class="bi bi-x-lg"></i>
            </button>
        `;

        // Focus the name input
        document.getElementById(`edit-name-${id}`)?.focus();
    },

    saveEditStaff(id) {
        const nameInput = document.getElementById(`edit-name-${id}`);
        const catSelect = document.getElementById(`edit-cat-${id}`);
        if (!nameInput || !catSelect) return;

        const newName = nameInput.value.trim();
        const newCat  = catSelect.value;

        if (!newName) {
            Utils.showToast('Name cannot be empty', 'warning');
            nameInput.focus();
            return;
        }

        const ok = this.updateStaff(id, newName, newCat);
        if (!ok) {
            Utils.showToast('Name already exists in that category', 'warning');
            return;
        }

        Utils.showToast(`Staff updated successfully`, 'success');
        this.refreshStaffTable();
    },

    cancelEditStaff(id) {
        this.refreshStaffTable();
    },

    // ── Main render ───────────────────────────────────────────────
    render() {
        const rooms = DB.getAll(DB.ROOMS);

        // Deduplicate: keep only the latest active task per room
        // NOTE: We only filter locally — never call DB.remove() here because
        // render() runs on every navigation and we must not fire MySQL DELETEs
        // on every page render.
        const allTasks = DB.getAll(DB.HOUSEKEEPING);
        const active = allTasks.filter(t => t.status === 'Dirty' || t.status === 'Cleaning' || t.status === 'Maintenance');
        const seen = {};
        active.forEach(t => {
            if (!seen[t.roomId]) {
                seen[t.roomId] = t;
            } else {
                const existing = seen[t.roomId];
                const existDate = new Date(existing.updatedAt || existing.createdAt || existing.assignedDate || 0);
                const curDate   = new Date(t.updatedAt || t.createdAt || t.assignedDate || 0);
                if (curDate > existDate) {
                    seen[t.roomId] = t;
                }
                // Do NOT call DB.remove() here — that fires a MySQL DELETE on every render
            }
        });

        const activeTasks = Object.values(seen);
        const staffList   = this.getStaff();

        return `
            <!-- ── Stat Cards ── -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${rooms.filter(r => r.status === 'Cleaning').length}</div>
                                <div class="stat-label">Rooms Being Cleaned</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-brush"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${activeTasks.filter(t => t.status === 'Dirty').length}</div>
                                <div class="stat-label">Dirty Rooms</div>
                            </div>
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${rooms.filter(r => r.status === 'Maintenance').length}</div>
                                <div class="stat-label">Under Maintenance</div>
                            </div>
                            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-wrench"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${rooms.filter(r => r.status === 'Available').length}</div>
                                <div class="stat-label">Clean & Ready</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Staff Management Panel ── -->
            <div class="module-card mb-4">
                <div class="card-header">
                    <span><i class="bi bi-people me-2"></i>Staff Management
                        <span class="badge bg-light text-dark ms-2" style="font-size:0.75rem">${staffList.length} staff</span>
                    </span>
                    <button class="btn btn-sm btn-light" onclick="HousekeepingModule.toggleStaffPanel()" id="staffPanelToggleBtn">
                        <i class="bi bi-chevron-down" id="staffPanelToggleIcon"></i>
                        <span id="staffPanelToggleLabel">Show</span>
                    </button>
                </div>
                <div class="card-body" id="staffPanelBody" style="display:none">
                    <div class="row g-4">

                        <!-- Add Staff Form -->
                        <div class="col-md-4">
                            <div style="background:#f8f6f2;border-radius:10px;padding:18px;border:1px solid #e8d9c0">
                                <h6 style="color:var(--primary);font-weight:700;margin-bottom:14px">
                                    <i class="bi bi-person-plus me-2" style="color:var(--accent)"></i>Add Staff Member
                                </h6>
                                <div class="mb-3">
                                    <label class="form-label" style="font-size:0.82rem;font-weight:600;color:#666">Full Name *</label>
                                    <input type="text" class="form-control form-control-sm" id="newStaffName"
                                           placeholder="e.g. Raju Kumar" maxlength="60"
                                           onkeydown="if(event.key==='Enter') HousekeepingModule.saveNewStaff()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" style="font-size:0.82rem;font-weight:600;color:#666">Category *</label>
                                    <select class="form-select form-select-sm" id="newStaffCategory">
                                        <option value="Cleaning">🧹 Cleaning</option>
                                        <option value="Maintenance">🔧 Maintenance</option>
                                    </select>
                                </div>
                                <button class="btn btn-accent btn-sm w-100" onclick="HousekeepingModule.saveNewStaff()">
                                    <i class="bi bi-plus-lg me-1"></i>Add Staff
                                </button>
                            </div>
                        </div>

                        <!-- Staff List Table -->
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 style="color:var(--primary);font-weight:700;margin:0">
                                    <i class="bi bi-list-ul me-2" style="color:var(--accent)"></i>Staff Directory
                                </h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-info"
                                            onclick="HousekeepingModule.filterStaffTable('Cleaning')" id="filterCleaning" style="font-size:0.75rem">
                                        <i class="bi bi-brush me-1"></i>Cleaning (${staffList.filter(s=>s.category==='Cleaning').length})
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            onclick="HousekeepingModule.filterStaffTable('Maintenance')" id="filterMaintenance" style="font-size:0.75rem">
                                        <i class="bi bi-wrench me-1"></i>Maintenance (${staffList.filter(s=>s.category==='Maintenance').length})
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="HousekeepingModule.filterStaffTable('')" id="filterAll" style="font-size:0.75rem">
                                        All (${staffList.length})
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height:240px;overflow-y:auto">
                                <table class="table data-table table-sm" id="staffDirectoryTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th style="width:90px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="staffTableBody">
                                        ${this.staffTableRows()}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Room Status Board + Active Tasks ── -->
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-grid me-2"></i>Room Status Board</span>
                            <button class="btn btn-sm btn-light" onclick="HousekeepingModule.showAssignForm()">
                                <i class="bi bi-plus-lg"></i> Assign Task
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="room-grid">
                                ${rooms.map(r => `
                                    <div class="room-cell ${(r.status || 'Available').toLowerCase()}"
                                         onclick="HousekeepingModule.updateRoomStatus('${r.id}')">
                                        <div class="room-number">${Utils.escapeHtml(r.roomNumber)}</div>
                                        <div class="room-type">${Utils.escapeHtml(r.roomType)}</div>
                                        <div class="mt-1">${Utils.statusBadge(r.status || 'Available')}</div>
                                    </div>
                                `).join('')}
                                ${rooms.length === 0 ? '<div class="empty-state"><i class="bi bi-door-closed d-block"></i>No rooms configured</div>' : ''}
                            </div>
                            <div class="mt-3 d-flex gap-3 flex-wrap">
                                <small><span class="badge badge-available">●</span> Available</small>
                                <small><span class="badge badge-occupied">●</span> Occupied</small>
                                <small><span class="badge badge-reserved">●</span> Reserved</small>
                                <small><span class="badge badge-cleaning">●</span> Cleaning</small>
                                <small><span class="badge badge-maintenance">●</span> Maintenance</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5" id="section-active-tasks">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-list-task me-2"></i>Active Tasks</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Status</th>
                                            <th>Staff</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${activeTasks.length === 0
                                            ? '<tr><td colspan="4" class="text-center py-4 text-muted">No pending tasks</td></tr>'
                                            : activeTasks.map(t => {
                                                const room = DB.getById(DB.ROOMS, t.roomId);
                                                // 'Dirty' is the cleaning-specific to-do label. A Maintenance
                                                // task that hasn't been started yet still has status 'Dirty'
                                                // (set by assignTask), but showing "Dirty" for a maintenance
                                                // job reads wrong to the admin — display "Pending" instead.
                                                // The underlying status value is untouched so Start/Done
                                                // buttons and filters keep working exactly as before.
                                                const displayStatus = (t.taskType === 'Maintenance')
                                                    ? (t.status === 'Dirty' ? 'Pending' : t.status === 'Cleaning' ? 'In Progress' : t.status)
                                                    : t.status;
                                                return `
                                                <tr>
                                                    <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                    <td>${Utils.statusBadge(displayStatus)}</td>
                                                    <td>${Utils.escapeHtml(t.assignedTo || 'Unassigned')}</td>
                                                    <td>
                                                        ${t.status === 'Dirty'    ? `<button class="btn btn-sm btn-outline-info"    onclick="HousekeepingModule.markCleaning('${t.id}')">Start</button>` : ''}
                                                        ${t.status === 'Cleaning' ? `<button class="btn btn-sm btn-outline-success" onclick="HousekeepingModule.markClean('${t.id}')">Done</button>`    : ''}
                                                        ${t.status === 'Maintenance' ? `<button class="btn btn-sm btn-outline-success" onclick="HousekeepingModule.markClean('${t.id}')">Done</button>` : ''}
                                                    </td>
                                                </tr>`;
                                            }).join('')
                                        }
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATUS UPDATE MODAL -->
            <div class="modal fade" id="hkStatusModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Room Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="hkStatusForm">
                                <input type="hidden" name="roomId" id="hkRoomId">
                                <div class="mb-3">
                                    <label class="form-label">Room: <strong id="hkRoomLabel"></strong></label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Status</label>
                                    <select class="form-select" name="status" id="hkNewStatus"
                                            onchange="HousekeepingModule.onStatusModalStatusChange()">
                                        ${Utils.housekeepingStatuses.map(s => `<option value="${s}">${s}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3" id="hkStatusStaffWrap">
                                    <label class="form-label d-flex align-items-center justify-content-between">
                                        <span>Assign To</span>
                                        <small class="text-muted" id="hkStatusStaffHint" style="font-size:0.75rem"></small>
                                    </label>
                                    <select class="form-select" name="assignedTo" id="hkStatusStaffSelect">
                                        ${this.staffDropdownOptions('')}
                                    </select>
                                    <div class="mt-2">
                                        <input type="text" class="form-control form-control-sm" id="hkStatusStaffManual"
                                               placeholder="Or type a name manually..."
                                               oninput="HousekeepingModule.onStatusManualInput()"
                                               maxlength="50">
                                        <small class="text-muted" style="font-size:0.72rem">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Type here to override the dropdown selection
                                        </small>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="HousekeepingModule.saveStatus()">
                                <i class="bi bi-check-lg me-1"></i>Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSIGN TASK MODAL -->
            <div class="modal fade" id="hkAssignModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background:var(--primary);color:#fff;border-radius:8px 8px 0 0">
                            <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Assign Cleaning Task</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="background:#fdfaf6">
                            <form id="hkAssignForm">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-door-open me-1" style="color:var(--accent)"></i>Room *
                                    </label>
                                    <select class="form-select" name="roomId" required>
                                        <option value="">Select Room</option>
                                        ${rooms.map(r => `<option value="${r.id}">${Utils.escapeHtml(r.roomNumber)} — ${Utils.escapeHtml(r.roomType)} (${Utils.escapeHtml(r.status || 'Available')})</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-tag me-1" style="color:var(--accent)"></i>Task Type *
                                    </label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="taskType" id="taskTypeCleaning"
                                                   value="Cleaning" checked
                                                   onchange="HousekeepingModule.onAssignTaskTypeChange()">
                                            <label class="form-check-label" for="taskTypeCleaning">
                                                <i class="bi bi-brush me-1 text-info"></i>Cleaning
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="taskType" id="taskTypeMaintenance"
                                                   value="Maintenance"
                                                   onchange="HousekeepingModule.onAssignTaskTypeChange()">
                                            <label class="form-check-label" for="taskTypeMaintenance">
                                                <i class="bi bi-wrench me-1 text-secondary"></i>Maintenance
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold d-flex align-items-center justify-content-between">
                                        <span><i class="bi bi-person me-1" style="color:var(--accent)"></i>Assign To</span>
                                        <a href="#" onclick="HousekeepingModule.openStaffPanel(); return false;"
                                           style="font-size:0.75rem;color:var(--accent)">
                                            <i class="bi bi-plus-circle me-1"></i>Manage Staff
                                        </a>
                                    </label>
                                    <select class="form-select" name="assignedTo" id="hkAssignStaffSelect">
                                        ${this.staffDropdownOptions('Cleaning')}
                                    </select>
                                    <div class="mt-2">
                                        <input type="text" class="form-control form-control-sm" id="hkAssignStaffManual"
                                               placeholder="Or type a name manually..."
                                               oninput="HousekeepingModule.onAssignManualInput()"
                                               maxlength="50">
                                        <small class="text-muted" style="font-size:0.72rem">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Type here to override the dropdown selection
                                        </small>
                                    </div>
                                    <div id="hkNoStaffWarning" style="display:none;margin-top:8px">
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            No staff in this category.
                                            <a href="#" onclick="HousekeepingModule.openStaffPanel(); return false;">Add staff first</a>
                                            or type a name above.
                                        </small>
                                    </div>
                                </div>
                                <div class="mb-1">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-flag me-1" style="color:var(--accent)"></i>Priority
                                    </label>
                                    <select class="form-select" name="priority">
                                        <option value="Normal">Normal</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer" style="background:#fdfaf6">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="HousekeepingModule.assignTask()">
                                <i class="bi bi-send me-1"></i>Assign
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // ── Staff Panel toggle ────────────────────────────────────────
    toggleStaffPanel() {
        const body  = document.getElementById('staffPanelBody');
        const icon  = document.getElementById('staffPanelToggleIcon');
        const label = document.getElementById('staffPanelToggleLabel');
        if (!body) return;
        const isHidden = body.style.display === 'none';
        body.style.display  = isHidden ? 'block' : 'none';
        icon.className      = isHidden ? 'bi bi-chevron-up'   : 'bi bi-chevron-down';
        label.textContent   = isHidden ? 'Hide'               : 'Show';
    },

    openStaffPanel() {
        const assignModal = document.getElementById('hkAssignModal');
        if (assignModal) {
            const bsModal = bootstrap.Modal.getInstance(assignModal);
            if (bsModal) bsModal.hide();
        }
        const body  = document.getElementById('staffPanelBody');
        const icon  = document.getElementById('staffPanelToggleIcon');
        const label = document.getElementById('staffPanelToggleLabel');
        if (body) {
            body.style.display = 'block';
            icon.className     = 'bi bi-chevron-up';
            label.textContent  = 'Hide';
            body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },

    // ── Save new staff ────────────────────────────────────────────
    saveNewStaff() {
        const nameEl = document.getElementById('newStaffName');
        const catEl  = document.getElementById('newStaffCategory');
        if (!nameEl || !catEl) return;

        const name     = nameEl.value.trim();
        const category = catEl.value;

        if (!name) {
            Utils.showToast('Please enter a staff name', 'warning');
            nameEl.focus();
            return;
        }

        const ok = this.addStaff(name, category);
        if (!ok) {
            Utils.showToast(`"${name}" already exists in ${category}`, 'warning');
            return;
        }

        nameEl.value = '';
        Utils.showToast(`${name} added to ${category} staff`, 'success');
        this.refreshStaffTable();
    },

    // ── Delete staff ──────────────────────────────────────────────
    deleteStaff(id) {
        const staff = this.getStaff().find(s => s.id === id);
        if (!staff) return;
        if (!Utils.confirmAction(`Remove "${staff.name}" from staff list?`)) return;
        this.removeStaff(id);
        Utils.showToast(`${staff.name} removed`, 'info');
        this.refreshStaffTable();
    },

    // ── Refresh just the staff table ─────────────────────────────
    refreshStaffTable() {
        const tbody = document.getElementById('staffTableBody');
        if (tbody) tbody.innerHTML = this.staffTableRows();

        const list = this.getStaff();
        const btnAll = document.getElementById('filterAll');
        const btnC   = document.getElementById('filterCleaning');
        const btnM   = document.getElementById('filterMaintenance');
        if (btnAll) btnAll.innerHTML = `All (${list.length})`;
        if (btnC)   btnC.innerHTML   = `<i class="bi bi-brush me-1"></i>Cleaning (${list.filter(s=>s.category==='Cleaning').length})`;
        if (btnM)   btnM.innerHTML   = `<i class="bi bi-wrench me-1"></i>Maintenance (${list.filter(s=>s.category==='Maintenance').length})`;

        const badge = document.querySelector('.card-header .badge.bg-light');
        if (badge) badge.textContent = `${list.length} staff`;
    },

    // ── Filter the staff directory table ─────────────────────────
    filterStaffTable(category) {
        document.querySelectorAll('#staffDirectoryTable tbody tr').forEach(row => {
            if (!category) {
                row.style.display = '';
            } else {
                const catCell = row.cells[1]?.textContent?.trim() || '';
                row.style.display = catCell.includes(category) ? '' : 'none';
            }
        });
    },

    // ── Status modal helpers ──────────────────────────────────────
    onStatusModalStatusChange() {
        const status = document.getElementById('hkNewStatus')?.value;
        const select = document.getElementById('hkStatusStaffSelect');
        const hint   = document.getElementById('hkStatusStaffHint');
        if (!select) return;

        let cat = '';
        if (status === 'Cleaning' || status === 'Dirty') {
            cat = 'Cleaning';
            if (hint) hint.textContent = 'Showing Cleaning staff';
        } else if (status === 'Maintenance') {
            cat = 'Maintenance';
            if (hint) hint.textContent = 'Showing Maintenance staff';
        } else {
            if (hint) hint.textContent = '';
        }
        select.innerHTML = this.staffDropdownOptions(cat);
        const manual = document.getElementById('hkStatusStaffManual');
        if (manual) manual.value = '';
    },

    onStatusManualInput() {
        const manual = document.getElementById('hkStatusStaffManual');
        const select = document.getElementById('hkStatusStaffSelect');
        if (manual && select && manual.value.trim()) select.value = '';
    },

    onAssignTaskTypeChange() {
        const radios = document.querySelectorAll('input[name="taskType"]');
        let taskType = 'Cleaning';
        radios.forEach(r => { if (r.checked) taskType = r.value; });

        const select  = document.getElementById('hkAssignStaffSelect');
        const warning = document.getElementById('hkNoStaffWarning');
        const manual  = document.getElementById('hkAssignStaffManual');
        if (!select) return;

        select.innerHTML = this.staffDropdownOptions(taskType);
        if (manual) manual.value = '';

        const hasStaff = this.getStaff().some(s => s.category === taskType);
        if (warning) warning.style.display = hasStaff ? 'none' : 'block';
    },

    onAssignManualInput() {
        const manual = document.getElementById('hkAssignStaffManual');
        const select = document.getElementById('hkAssignStaffSelect');
        if (manual && select && manual.value.trim()) select.value = '';
    },

    _resolveStaffName(selectId, manualId) {
        const manual = (document.getElementById(manualId)?.value || '').trim();
        if (manual) return manual;
        const sel = document.getElementById(selectId);
        return (sel?.value || '').trim();
    },

    // ── Room click → Status Update modal ─────────────────────────
    updateRoomStatus(roomId) {
        const room = DB.getById(DB.ROOMS, roomId);
        if (!room) return;
        document.getElementById('hkRoomId').value         = roomId;
        document.getElementById('hkRoomLabel').textContent = room.roomNumber + ' (' + room.roomType + ')';
        document.getElementById('hkNewStatus').value      = room.status === 'Cleaning' ? 'Cleaning' : 'Dirty';
        this.onStatusModalStatusChange();
        new bootstrap.Modal(document.getElementById('hkStatusModal')).show();
    },

    saveStatus() {
        const data = Utils.getFormData('hkStatusForm');
        if (!data.roomId) return;

        data.assignedTo = this._resolveStaffName('hkStatusStaffSelect', 'hkStatusStaffManual');

        let roomStatus = data.status;
        if (data.status === 'Clean')                                    roomStatus = 'Available';
        else if (data.status === 'Dirty' || data.status === 'Cleaning') roomStatus = 'Cleaning';
        else if (data.status === 'Maintenance')                         roomStatus = 'Maintenance';

        // A mid-stay clean must never flip an Occupied room to Available,
        // and 'Cleaning'/'Dirty' assigned mid-stay must not evict the guest's
        // Occupied status either. Only post-checkout (no active checkin)
        // cleans should change the room's bookable status.
        const activeCheckin = DB.query(DB.CHECKINS, c =>
            c.roomId === data.roomId && c.status === 'Checked-In'
        ).length > 0;

        if (activeCheckin) {
            if (roomStatus === 'Maintenance') {
                // Maintenance can still be flagged even with a guest inside
                // (e.g. broken AC) — admin needs this visible regardless.
                DB.update(DB.ROOMS, data.roomId, { status: 'Maintenance' });
            } else {
                // 'Available' or 'Cleaning' results are suppressed — room
                // stays Occupied while the guest is checked in.
                DB.update(DB.ROOMS, data.roomId, { status: 'Occupied' });
            }
            Utils.showToast('Mid-stay update saved — room status unaffected', 'info');
        } else {
            DB.update(DB.ROOMS, data.roomId, { status: roomStatus });
        }

        const taskType = data.status === 'Maintenance' ? 'Maintenance' : 'Cleaning';

        const existing = DB.query(DB.HOUSEKEEPING, t =>
            t.roomId === data.roomId && (t.status === 'Dirty' || t.status === 'Cleaning' || t.status === 'Maintenance')
        );
        if (existing.length > 0) {
            DB.update(DB.HOUSEKEEPING, existing[0].id, {
                status: data.status,
                assignedTo: data.assignedTo,
                taskType,
                assignedDate: Utils.today()
            });
        } else {
            DB.add(DB.HOUSEKEEPING, {
                roomId: data.roomId,
                status: data.status,
                assignedTo: data.assignedTo,
                taskType,
                assignedDate: Utils.today()
            });
        }

        bootstrap.Modal.getInstance(document.getElementById('hkStatusModal')).hide();
        Utils.showToast('Room status updated');
        LodgeApp.navigate('housekeeping');
    },

    showAssignForm() {
        const form = document.getElementById('hkAssignForm');
        if (form) form.reset();
        const manualField = document.getElementById('hkAssignStaffManual');
        if (manualField) manualField.value = '';

        const sel = document.getElementById('hkAssignStaffSelect');
        if (sel) sel.innerHTML = this.staffDropdownOptions('Cleaning');

        const hasStaff = this.getStaff().some(s => s.category === 'Cleaning');
        const warning  = document.getElementById('hkNoStaffWarning');
        if (warning) warning.style.display = hasStaff ? 'none' : 'block';

        new bootstrap.Modal(document.getElementById('hkAssignModal')).show();
    },

    assignTask() {
        const data = Utils.getFormData('hkAssignForm');
        if (!data.roomId) {
            Utils.showToast('Please select a room', 'warning');
            return;
        }

        data.assignedTo = this._resolveStaffName('hkAssignStaffSelect', 'hkAssignStaffManual');

        const radios   = document.querySelectorAll('input[name="taskType"]');
        let taskType   = 'Cleaning';
        radios.forEach(r => { if (r.checked) taskType = r.value; });
        const roomStatus = taskType === 'Maintenance' ? 'Maintenance' : 'Cleaning';

        const existing = DB.query(DB.HOUSEKEEPING, t =>
            t.roomId === data.roomId && (t.status === 'Dirty' || t.status === 'Cleaning')
        );
        if (existing.length > 0) {
            DB.update(DB.HOUSEKEEPING, existing[0].id, {
                status: 'Dirty',
                assignedTo: data.assignedTo,
                priority: data.priority,
                taskType,
                assignedDate: Utils.today()
            });
        } else {
            DB.add(DB.HOUSEKEEPING, {
                roomId: data.roomId,
                status: 'Dirty',
                assignedTo: data.assignedTo,
                priority: data.priority,
                taskType,
                assignedDate: Utils.today()
            });
        }
        // Same guard as saveStatus()/markClean(): if the guest is currently
        // checked in, this is a mid-stay clean request — the room must
        // remain Occupied throughout, not flip to Cleaning/Maintenance
        // and then get stuck there after the task is completed.
        const activeCheckin = DB.query(DB.CHECKINS, c =>
            c.roomId === data.roomId && c.status === 'Checked-In'
        ).length > 0;

        if (!activeCheckin) {
            DB.update(DB.ROOMS, data.roomId, { status: roomStatus });
        }

        bootstrap.Modal.getInstance(document.getElementById('hkAssignModal')).hide();
        Utils.showToast(
            `Task assigned to ${data.assignedTo || 'staff'}` +
            (data.priority && data.priority !== 'Normal' ? ` · Priority: ${data.priority}` : ''),
            'success'
        );
        LodgeApp.navigate('housekeeping');
    },

    markCleaning(taskId) {
        const task = DB.getById(DB.HOUSEKEEPING, taskId);
        DB.update(DB.HOUSEKEEPING, taskId, { status: 'Cleaning' });
        Utils.showToast(task && task.taskType === 'Maintenance' ? 'Maintenance in progress' : 'Cleaning in progress');
        LodgeApp.navigate('housekeeping');
    },

    markClean(taskId) {
        const task = DB.getById(DB.HOUSEKEEPING, taskId);
        DB.update(DB.HOUSEKEEPING, taskId, { status: 'Clean', completedAt: new Date().toISOString() });

        const isMaintenance = task && task.taskType === 'Maintenance';
        const doneLabel     = isMaintenance ? 'Maintenance' : 'Cleaning';

        if (task && task.roomId) {
            // Same guard as saveStatus(): a mid-stay task must never flip
            // an Occupied room to Available while the guest is still
            // checked in. Only post-checkout tasks should free up the room.
            const activeCheckin = DB.query(DB.CHECKINS, c =>
                c.roomId === task.roomId && c.status === 'Checked-In'
            ).length > 0;

            if (!activeCheckin) {
                DB.update(DB.ROOMS, task.roomId, { status: 'Available' });
                Utils.showToast(isMaintenance ? 'Maintenance completed — room is ready' : 'Room marked as clean and ready');
            } else {
                // Mid-stay task finished — restore Occupied (room must not
                // stay stuck on 'Cleaning'/'Maintenance' while the guest is still inside)
                DB.update(DB.ROOMS, task.roomId, { status: 'Occupied' });
                Utils.showToast(`Mid-stay ${doneLabel.toLowerCase()} completed — room is Occupied again`, 'info');
            }
        } else {
            Utils.showToast('Task marked as done');
        }

        LodgeApp.navigate('housekeeping');
    }
};
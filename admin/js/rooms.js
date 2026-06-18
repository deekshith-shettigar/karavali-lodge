/* ==========================================
   Room Management Module
   ========================================== */
const RoomModule = {
    _view: 'floor', // 'floor' | 'grid' | 'list'

    render() {
        const rooms = DB.getAll(DB.ROOMS);

        // ── Status counts ────────────────────────────────────────
        const counts = { Available:0, Occupied:0, Reserved:0, Cleaning:0, Maintenance:0 };
        rooms.forEach(r => { const s = r.status || 'Available'; if (counts[s] !== undefined) counts[s]++; });

        const statusColors = {
            Available:   '#28a745',
            Occupied:    '#dc3545',
            Reserved:    '#ffc107',
            Cleaning:    '#17a2b8',
            Maintenance: '#6c757d',
        };

        const legendHtml = Object.entries(counts).map(([s, c]) =>
            `<span style="display:inline-flex;align-items:center;gap:5px;font-size:0.83rem;cursor:pointer;padding:4px 10px;
                          border-radius:20px;border:1.5px solid ${statusColors[s]}20;background:${statusColors[s]}10;
                          transition:all 0.15s;" title="Filter: ${s}"
                   onclick="RoomModule._filterByStatus('${s}')">
                <span style="width:10px;height:10px;border-radius:50%;background:${statusColors[s]};flex-shrink:0;"></span>
                <strong style="color:${statusColors[s]}">${s}:</strong>
                <span style="color:#333;font-weight:700">${c}</span>
            </span>`
        ).join('');

        return `
            <!-- Filter bar -->
            <div class="filter-bar">
                <input type="text" class="form-control" id="roomSearch" placeholder="Search rooms..." oninput="RoomModule.filterRooms()">
                <select class="form-select" id="roomTypeFilter" onchange="RoomModule.filterRooms()">
                    <option value="">All Types</option>
                    ${Utils.roomTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                </select>
                <select class="form-select" id="roomStatusFilter" onchange="RoomModule.filterRooms()">
                    <option value="">All Status</option>
                    ${Utils.roomStatuses.map(s => `<option value="${s}">${s}</option>`).join('')}
                </select>
                <button class="btn btn-accent ms-auto" onclick="RoomModule.showForm()">
                    <i class="bi bi-plus-lg"></i> Add Room
                </button>
            </div>

            <!-- Status Legend -->
            <div style="background:#fff;border-radius:10px;padding:12px 16px;
                        box-shadow:var(--card-shadow);margin-bottom:16px;
                        display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span style="font-size:0.78rem;font-weight:600;color:#888;text-transform:uppercase;
                             letter-spacing:0.8px;margin-right:4px;">Status:</span>
                ${legendHtml}
                <button onclick="RoomModule._clearStatusFilter()"
                        style="margin-left:auto;background:none;border:1px solid #dee2e6;border-radius:20px;
                               padding:3px 12px;font-size:0.78rem;color:#888;cursor:pointer;"
                        title="Clear filter">Clear</button>
            </div>

            <!-- View toggle + Room Overview header -->
            <div class="module-card mb-4">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <span><i class="bi bi-grid-3x3-gap me-2"></i>Room Overview</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="badge bg-light text-dark">${rooms.length} rooms</span>
                        <!-- View toggle buttons -->
                        <div style="display:flex;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <button id="btnViewFloor" onclick="RoomModule.setView('floor')"
                                    title="Floor View"
                                    style="padding:5px 12px;border:none;font-size:0.82rem;cursor:pointer;
                                           background:${RoomModule._view==='floor'?'#3B1A0A':'#fff'};
                                           color:${RoomModule._view==='floor'?'#C4943A':'#666'};
                                           transition:all 0.2s;">
                                <i class="bi bi-building"></i> Floor
                            </button>
                            <button id="btnViewGrid" onclick="RoomModule.setView('grid')"
                                    title="Grid View"
                                    style="padding:5px 12px;border:none;border-left:1px solid #dee2e6;font-size:0.82rem;cursor:pointer;
                                           background:${RoomModule._view==='grid'?'#3B1A0A':'#fff'};
                                           color:${RoomModule._view==='grid'?'#C4943A':'#666'};
                                           transition:all 0.2s;">
                                <i class="bi bi-grid-3x3-gap"></i> Grid
                            </button>
                            <button id="btnViewList" onclick="RoomModule.setView('list')"
                                    title="List View"
                                    style="padding:5px 12px;border:none;border-left:1px solid #dee2e6;font-size:0.82rem;cursor:pointer;
                                           background:${RoomModule._view==='list'?'#3B1A0A':'#fff'};
                                           color:${RoomModule._view==='list'?'#C4943A':'#666'};
                                           transition:all 0.2s;">
                                <i class="bi bi-list-ul"></i> List
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="roomOverviewBody">
                    ${RoomModule._renderOverview(rooms)}
                </div>
            </div>

            <!-- Room Form Modal -->
            <div class="modal fade" id="roomModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="roomModalTitle">Add Room</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="roomForm">
                                <input type="hidden" name="id" id="roomId">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Room Number *</label>
                                        <input type="text" class="form-control" name="roomNumber" required maxlength="10">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Room Type *</label>
                                        <select class="form-select" name="roomType" required>
                                            ${Utils.roomTypes.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Floor</label>
                                        <input type="text" class="form-control" name="floor" maxlength="10">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Price Per Night (₹) *</label>
                                        <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Capacity</label>
                                        <input type="number" class="form-control" name="capacity" min="1" max="20" value="2">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            ${Utils.roomStatuses.map(s => `<option value="${s}">${s}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Amenities</label>
                                        <div class="dropdown w-100" id="amenitiesDropdown">
                                            <button class="form-select text-start w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="amenitiesDropdownBtn">
                                                <span class="text-muted" id="amenitiesPlaceholder">Select amenities...</span>
                                            </button>
                                            <ul class="dropdown-menu w-100 p-2" style="max-height:220px;overflow-y:auto;" id="amenitiesMenu">
                                                ${RoomModule._getServiceOptions()}
                                            </ul>
                                        </div>
                                        <input type="hidden" name="amenities" id="amenitiesHidden">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="RoomModule.saveRoom()">Save Room</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // ── Render the overview section based on current view ──────
    _renderOverview(rooms) {
        if (!rooms) rooms = DB.getAll(DB.ROOMS);
        if (rooms.length === 0) {
            return '<div class="empty-state"><i class="bi bi-door-closed d-block"></i>No rooms added yet</div>';
        }

        if (this._view === 'floor') return this._renderFloorView(rooms);
        if (this._view === 'grid')  return this._renderGridView(rooms);
        if (this._view === 'list')  return this._renderListView(rooms);
    },

    // ── FLOOR VIEW — grouped by floor ──────────────────────────
    _renderFloorView(rooms) {
        // Group by floor
        const floors = {};
        rooms.forEach(r => {
            const f = r.floor || 'G';
            if (!floors[f]) floors[f] = [];
            floors[f].push(r);
        });

        // Sort floors: G first, then 1, 2, 3...
        const floorOrder = Object.keys(floors).sort((a, b) => {
            if (a === 'G') return -1;
            if (b === 'G') return 1;
            return parseInt(a) - parseInt(b);
        });

        return floorOrder.map(floor => {
            const floorRooms = floors[floor];
            const occupied  = floorRooms.filter(r => (r.status||'Available') === 'Occupied').length;
            const available = floorRooms.filter(r => (r.status||'Available') === 'Available').length;

            return `
                <div style="margin-bottom:24px;" class="floor-section" data-floor="${floor}">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;
                                padding-bottom:8px;border-bottom:2px solid #f0e8d8;">
                        <div style="background:linear-gradient(135deg,#3B1A0A,#5C2E15);color:#C4943A;
                                    border-radius:8px;padding:4px 14px;font-size:0.82rem;font-weight:700;
                                    letter-spacing:0.5px;">
                            ${floor === 'G' ? 'Ground Floor' : `Floor ${floor}`}
                        </div>
                        <span style="font-size:0.78rem;color:#888">${floorRooms.length} rooms</span>
                        <span style="font-size:0.78rem;color:#28a745;font-weight:600">${available} available</span>
                        ${occupied > 0 ? `<span style="font-size:0.78rem;color:#dc3545;font-weight:600">${occupied} occupied</span>` : ''}
                    </div>
                    <div class="room-grid">
                        ${floorRooms.map(r => `
                            <div class="room-cell ${(r.status || 'Available').toLowerCase()}"
                                 onclick="RoomModule.showDetails('${r.id}')"
                                 data-status="${r.status || 'Available'}">
                                <div class="room-number">${Utils.escapeHtml(r.roomNumber)}</div>
                                <div class="room-type">${Utils.escapeHtml(r.roomType)}</div>
                                <div class="mt-1">${Utils.statusBadge(r.status || 'Available')}</div>
                                <div class="text-muted" style="font-size:0.72rem">${Utils.formatCurrency(r.price)}/night</div>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
        }).join('');
    },

    // ── GRID VIEW — all rooms in one flat grid ──────────────────
    _renderGridView(rooms) {
        return `<div class="room-grid" id="roomGrid">
            ${rooms.map(r => `
                <div class="room-cell ${(r.status || 'Available').toLowerCase()}"
                     onclick="RoomModule.showDetails('${r.id}')"
                     data-status="${r.status || 'Available'}">
                    <div class="room-number">${Utils.escapeHtml(r.roomNumber)}</div>
                    <div class="room-type">${Utils.escapeHtml(r.roomType)}</div>
                    <div class="mt-1">${Utils.statusBadge(r.status || 'Available')}</div>
                    <div class="text-muted" style="font-size:0.75rem">${Utils.formatCurrency(r.price)}/night</div>
                </div>
            `).join('')}
        </div>`;
    },

    // ── LIST VIEW — detailed table ──────────────────────────────
    _renderListView(rooms) {
        return `
        <div class="table-responsive">
            <table class="table data-table" id="roomTable">
                <thead>
                    <tr>
                        <th>Room No</th><th>Type</th><th>Floor</th>
                        <th>Price/Night</th><th>Capacity</th>
                        <th>Status</th><th>Amenities</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${rooms.map(r => `
                        <tr data-room-id="${r.id}" data-status="${r.status || 'Available'}">
                            <td><strong>${Utils.escapeHtml(r.roomNumber)}</strong></td>
                            <td>${Utils.escapeHtml(r.roomType)}</td>
                            <td>${Utils.escapeHtml(r.floor || '-')}</td>
                            <td>${Utils.formatCurrency(r.price)}</td>
                            <td>${r.capacity || '-'}</td>
                            <td>${Utils.statusBadge(r.status || 'Available')}</td>
                            <td><small>${Utils.escapeHtml(r.amenities || '-')}</small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="RoomModule.showForm('${r.id}')" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-danger" onclick="RoomModule.deleteRoom('${r.id}')" title="Delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>`;
    },

    // ── Switch view ─────────────────────────────────────────────
    setView(view) {
        this._view = view;
        const rooms = DB.getAll(DB.ROOMS);

        // Re-render overview section only (no full page reload)
        const body = document.getElementById('roomOverviewBody');
        if (body) body.innerHTML = this._renderOverview(rooms);

        // Update toggle button styles
        ['floor','grid','list'].forEach(v => {
            const btn = document.getElementById('btnView' + v.charAt(0).toUpperCase() + v.slice(1));
            if (btn) {
                btn.style.background = v === view ? '#3B1A0A' : '#fff';
                btn.style.color      = v === view ? '#C4943A' : '#666';
            }
        });

        // Re-apply any active status filter
        const statusFilter = document.getElementById('roomStatusFilter');
        if (statusFilter && statusFilter.value) {
            this._filterByStatus(statusFilter.value);
        }
    },

    // ── Filter by status from legend dot click ──────────────────
    _filterByStatus(status) {
        const sel = document.getElementById('roomStatusFilter');
        if (sel) {
            sel.value = status;
            this.filterRooms();
        }
    },

    _clearStatusFilter() {
        const sel = document.getElementById('roomStatusFilter');
        if (sel) { sel.value = ''; this.filterRooms(); }
        const srch = document.getElementById('roomSearch');
        if (srch) { srch.value = ''; this.filterRooms(); }
        const type = document.getElementById('roomTypeFilter');
        if (type) { type.value = ''; this.filterRooms(); }
    },

    _getServiceOptions() {
        let services = DB.getAll(DB.SERVICE_MENU);
        if (services.length === 0) {
            AmenityModule.defaultServices.forEach(s => DB.add(DB.SERVICE_MENU, s));
            services = DB.getAll(DB.SERVICE_MENU);
        }
        return services.map(s =>
            `<li><label class="dropdown-item d-flex align-items-center gap-2 py-1" style="cursor:pointer;"><input type="checkbox" class="form-check-input m-0" value="${Utils.escapeHtml(s.name)}"> ${Utils.escapeHtml(s.name)}</label></li>`
        ).join('');
    },

    _updateAmenitiesLabel() {
        const checks = document.querySelectorAll('#amenitiesMenu input[type=checkbox]:checked');
        const names = Array.from(checks).map(c => c.value);
        const placeholder = document.getElementById('amenitiesPlaceholder');
        const hidden = document.getElementById('amenitiesHidden');
        if (names.length === 0) {
            placeholder.textContent = 'Select amenities...';
            placeholder.classList.add('text-muted');
        } else {
            placeholder.textContent = names.join(', ');
            placeholder.classList.remove('text-muted');
        }
        hidden.value = names.join(', ');
    },

    showForm(id) {
        const modal = new bootstrap.Modal(document.getElementById('roomModal'));
        const form = document.getElementById('roomForm');
        form.reset();
        document.querySelectorAll('#amenitiesMenu input[type=checkbox]').forEach(c => c.checked = false);
        if (id) {
            const room = DB.getById(DB.ROOMS, id);
            if (room) {
                document.getElementById('roomModalTitle').textContent = 'Edit Room';
                Utils.populateForm('roomForm', room);
                if (room.amenities) {
                    const selected = room.amenities.split(',').map(a => a.trim().toLowerCase());
                    document.querySelectorAll('#amenitiesMenu input[type=checkbox]').forEach(c => {
                        if (selected.includes(c.value.toLowerCase())) c.checked = true;
                    });
                }
            }
        } else {
            document.getElementById('roomModalTitle').textContent = 'Add Room';
            document.getElementById('roomId').value = '';
        }
        this._updateAmenitiesLabel();
        document.querySelectorAll('#amenitiesMenu input[type=checkbox]').forEach(c => {
            c.onchange = () => RoomModule._updateAmenitiesLabel();
        });
        modal.show();
    },

    saveRoom() {
        const data = Utils.getFormData('roomForm');
        if (!data.roomNumber || !data.roomType || !data.price) {
            Utils.showToast('Please fill all required fields', 'warning');
            return;
        }
        data.price    = parseFloat(data.price);
        data.capacity = parseInt(data.capacity) || 2;

        if (data.id) {
            // Warn if changing status away from Occupied while guest is checked in
            const activeCheckin = DB.query(DB.CHECKINS, ci => ci.roomId === data.id && ci.status === 'Checked-In').length > 0;
            if (activeCheckin && data.status !== 'Occupied') {
                Utils.showToast(`⚠ Room has an active guest. Status kept as Occupied.`, 'warning');
                data.status = 'Occupied';
            }
            DB.update(DB.ROOMS, data.id, data);
            Utils.showToast('Room updated successfully');
        } else {
            const existing = DB.query(DB.ROOMS, r => r.roomNumber === data.roomNumber);
            if (existing.length > 0) {
                Utils.showToast('Room number already exists', 'danger');
                return;
            }
            delete data.id;
            DB.add(DB.ROOMS, data);
            Utils.showToast('Room added successfully');
        }
        bootstrap.Modal.getInstance(document.getElementById('roomModal')).hide();
        LodgeApp.navigate('rooms');
    },

    deleteRoom(id) {
        // Block deletion if room has active checkins or upcoming bookings
        const activeCheckin = DB.query(DB.CHECKINS, c => c.roomId === id && c.status === 'Checked-In').length > 0;
        const activeBooking = DB.query(DB.BOOKINGS, b => b.roomId === id && ['Confirmed','Pending','Checked-In'].includes(b.status)).length > 0;
        if (activeCheckin) {
            Utils.showToast('Cannot delete — a guest is currently checked in to this room.', 'danger');
            return;
        }
        if (activeBooking) {
            Utils.showToast('Cannot delete — this room has active or upcoming bookings.', 'danger');
            return;
        }
        if (!Utils.confirmAction('Are you sure you want to delete this room? This cannot be undone.')) return;
        DB.remove(DB.ROOMS, id);
        Utils.showToast('Room deleted', 'info');
        LodgeApp.navigate('rooms');
    },

    showDetails(id) {
        this.showForm(id);
    },

    filterRooms() {
        const search       = (document.getElementById('roomSearch')?.value || '').toLowerCase();
        const typeFilter   = document.getElementById('roomTypeFilter')?.value || '';
        const statusFilter = document.getElementById('roomStatusFilter')?.value || '';

        // Filter room cells (floor + grid view)
        document.querySelectorAll('[data-status].room-cell').forEach(cell => {
            const text   = cell.textContent.toLowerCase();
            const status = cell.getAttribute('data-status') || '';
            const matchSearch = !search || text.includes(search);
            const matchStatus = !statusFilter || status === statusFilter;
            cell.style.display = (matchSearch && matchStatus) ? '' : 'none';
        });

        // Hide empty floor sections in floor view
        document.querySelectorAll('.floor-section').forEach(section => {
            const visibleCells = section.querySelectorAll('.room-cell:not([style*="none"])');
            section.style.display = visibleCells.length > 0 ? '' : 'none';
        });

        // Filter table rows (list view)
        document.querySelectorAll('#roomTable tbody tr[data-room-id]').forEach(row => {
            const roomId = row.getAttribute('data-room-id');
            const room   = DB.getById(DB.ROOMS, roomId);
            if (!room) { row.style.display = 'none'; return; }
            const text = row.textContent.toLowerCase();
            const matchSearch = !search || text.includes(search);
            const matchType   = !typeFilter   || room.roomType === typeFilter;
            const matchStatus = !statusFilter || (room.status || 'Available') === statusFilter;
            row.style.display = (matchSearch && matchType && matchStatus) ? '' : 'none';
        });
    }
};
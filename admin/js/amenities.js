/* ==========================================
   Amenity / Service Management Module
   Karavali Lodge - Lodge Management
   ========================================== */
const AmenityModule = {
    defaultServices: [
        { name: 'Laundry', price: 200, category: 'Housekeeping' },
        { name: 'Extra Bed', price: 500, category: 'Room' },
        { name: 'Airport Pickup', price: 1500, category: 'Transport' },
        { name: 'Airport Drop', price: 1500, category: 'Transport' },
        { name: 'WiFi Premium', price: 100, category: 'Technology' },
        { name: 'Spa - Basic', price: 800, category: 'Wellness' },
        { name: 'Spa - Premium', price: 1500, category: 'Wellness' },
        { name: 'Mini Bar Refill', price: 300, category: 'Food' },
        { name: 'Early Check-In', price: 500, category: 'Room' },
        { name: 'Late Check-Out', price: 500, category: 'Room' },
        { name: 'Iron & Board', price: 100, category: 'Housekeeping' },
        { name: 'Baby Crib', price: 300, category: 'Room' }
    ],

    render() {
        let services = DB.getAll(DB.SERVICE_MENU);
        if (services.length === 0) {
            this.defaultServices.forEach(s => DB.add(DB.SERVICE_MENU, s));
            services = DB.getAll(DB.SERVICE_MENU);
        }

        const guestServices = DB.getAll(DB.GUEST_SERVICES);
        const checkins = DB.getAll(DB.CHECKINS).filter(c => c.status === 'Checked-In');
        const totalServiceRevenue = guestServices.reduce((sum, gs) => sum + (parseFloat(gs.charge) || 0), 0);

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${services.length}</div>
                                <div class="stat-label">Available Services</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-stars"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${guestServices.length}</div>
                                <div class="stat-label">Services Assigned</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${Utils.formatCurrency(totalServiceRevenue)}</div>
                                <div class="stat-label">Service Revenue</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-currency-rupee"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-grid me-2"></i>Service Catalog</span>
                            <button class="btn btn-sm btn-light" onclick="AmenityModule.showAddService()">
                                <i class="bi bi-plus-lg"></i> Add Service
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${services.map(s => `
                                            <tr>
                                                <td><strong>${Utils.escapeHtml(s.name)}</strong></td>
                                                <td><small class="badge bg-secondary">${Utils.escapeHtml(s.category || '-')}</small></td>
                                                <td>${Utils.formatCurrency(s.price)}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="AmenityModule.editService('${s.id}')" title="Edit"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-outline-danger" onclick="AmenityModule.deleteService('${s.id}')" title="Delete"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6" id="section-assign-service">
                    <div class="module-card mb-4">
                        <div class="card-header">
                            <span><i class="bi bi-person-plus me-2"></i>Assign Service to Guest</span>
                        </div>
                        <div class="card-body">
                            <form id="assignServiceForm">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Guest (Checked-In) *</label>
                                        <select class="form-select" name="checkinId" required>
                                            <option value="">Select Guest</option>
                                            ${checkins.map(c => {
                                                const room = DB.getById(DB.ROOMS, c.roomId);
                                                return `<option value="${c.id}">Room ${room ? Utils.escapeHtml(room.roomNumber) : '?'} - ${Utils.escapeHtml(c.guestName)}</option>`;
                                            }).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Service *</label>
                                        <select class="form-select" name="serviceId" onchange="AmenityModule.updatePrice()">
                                            <option value="">Select Service</option>
                                            ${services.map(s => `<option value="${s.id}" data-price="${s.price}">${Utils.escapeHtml(s.name)} - ${Utils.formatCurrency(s.price)}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Charge (₹)</label>
                                        <input type="number" class="form-control" name="charge" id="serviceCharge" min="0" step="0.01">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notes</label>
                                        <input type="text" class="form-control" name="notes" maxlength="200" placeholder="Any special notes...">
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-accent w-100" onclick="AmenityModule.assignService()">
                                            <i class="bi bi-plus-circle me-2"></i>Assign Service
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-list me-2"></i>Assigned Services</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Guest</th>
                                            <th>Service</th>
                                            <th>Charge</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${guestServices.length === 0 ? '<tr><td colspan="5" class="text-center py-3 text-muted">No services assigned</td></tr>' :
                                        guestServices.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).map(gs => {
                                            const checkin = DB.getById(DB.CHECKINS, gs.checkinId);
                                            return `
                                            <tr>
                                                <td>${checkin ? Utils.escapeHtml(checkin.guestName) : '-'}</td>
                                                <td>${Utils.escapeHtml(gs.serviceName)}</td>
                                                <td>${Utils.formatCurrency(gs.charge)}</td>
                                                <td>${Utils.formatDate(gs.createdAt)}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" title="Edit" onclick="AmenityModule.editAssignedService('${gs.id}')"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-outline-danger" title="Delete" onclick="AmenityModule.deleteAssignedService('${gs.id}')"><i class="bi bi-trash"></i></button>
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

            <!-- Add/Edit Service Catalog Modal -->
            <div class="modal fade" id="serviceModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="serviceModalTitle">Add Service</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="serviceForm">
                                <input type="hidden" name="id" id="serviceId">
                                <div class="mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" class="form-control" name="name" required maxlength="100">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" class="form-control" name="category" maxlength="50" placeholder="e.g., Housekeeping, Transport">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Price (₹) *</label>
                                    <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="AmenityModule.saveService()">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Assigned Service Modal -->
            <div class="modal fade" id="editAssignedServiceModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Assigned Service</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editAssignedServiceForm">
                                <input type="hidden" id="editAssignedServiceId">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Guest</label>
                                    <input type="text" class="form-control" id="editAssignedGuestName" readonly style="background:#f8f9fa">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Service Name</label>
                                    <input type="text" class="form-control" id="editAssignedServiceName" maxlength="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Charge (₹)</label>
                                    <input type="number" class="form-control" id="editAssignedCharge" min="0" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <input type="text" class="form-control" id="editAssignedNotes" maxlength="200" placeholder="Any special notes...">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="AmenityModule.saveAssignedService()">
                                <i class="bi bi-check2 me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    updatePrice() {
        const form = document.getElementById('assignServiceForm');
        const selected = form.elements.serviceId.selectedOptions[0];
        if (selected && selected.dataset.price) {
            document.getElementById('serviceCharge').value = selected.dataset.price;
        }
    },

    assignService() {
        const data = Utils.getFormData('assignServiceForm');
        if (!data.checkinId || !data.serviceId) {
            Utils.showToast('Select guest and service', 'warning');
            return;
        }
        const service = DB.getById(DB.SERVICE_MENU, data.serviceId);
        DB.add(DB.GUEST_SERVICES, {
            checkinId: data.checkinId,
            serviceId: data.serviceId,
            serviceName: service ? service.name : 'Unknown',
            charge: parseFloat(data.charge) || (service ? service.price : 0),
            notes: data.notes
        });
        Utils.showToast('Service assigned to guest');
        LodgeApp.navigate('amenities');
    },

    showAddService() {
        document.getElementById('serviceForm').reset();
        document.getElementById('serviceId').value = '';
        document.getElementById('serviceModalTitle').textContent = 'Add Service';
        new bootstrap.Modal(document.getElementById('serviceModal')).show();
    },

    editService(id) {
        const service = DB.getById(DB.SERVICE_MENU, id);
        if (!service) return;
        document.getElementById('serviceModalTitle').textContent = 'Edit Service';
        Utils.populateForm('serviceForm', service);
        new bootstrap.Modal(document.getElementById('serviceModal')).show();
    },

    saveService() {
        const data = Utils.getFormData('serviceForm');
        if (!data.name || !data.price) {
            Utils.showToast('Name and price required', 'warning');
            return;
        }
        data.price = parseFloat(data.price);
        if (data.id) {
            DB.update(DB.SERVICE_MENU, data.id, data);
            Utils.showToast('Service updated');
        } else {
            delete data.id;
            DB.add(DB.SERVICE_MENU, data);
            Utils.showToast('Service added');
        }
        bootstrap.Modal.getInstance(document.getElementById('serviceModal')).hide();
        LodgeApp.navigate('amenities');
    },

    deleteService(id) {
        if (!Utils.confirmAction('Delete this service from the catalog?')) return;
        DB.remove(DB.SERVICE_MENU, id);
        Utils.showToast('Service deleted', 'info');
        LodgeApp.navigate('amenities');
    },

    editAssignedService(id) {
        const gs = DB.getById(DB.GUEST_SERVICES, id);
        if (!gs) return;
        const checkin = DB.getById(DB.CHECKINS, gs.checkinId);

        document.getElementById('editAssignedServiceId').value = id;
        document.getElementById('editAssignedGuestName').value = checkin ? checkin.guestName : '-';
        document.getElementById('editAssignedServiceName').value = gs.serviceName || '';
        document.getElementById('editAssignedCharge').value = gs.charge || '';
        document.getElementById('editAssignedNotes').value = gs.notes || '';

        new bootstrap.Modal(document.getElementById('editAssignedServiceModal')).show();
    },

    saveAssignedService() {
        const id          = document.getElementById('editAssignedServiceId').value;
        const serviceName = document.getElementById('editAssignedServiceName').value.trim();
        const charge      = parseFloat(document.getElementById('editAssignedCharge').value) || 0;
        const notes       = document.getElementById('editAssignedNotes').value.trim();

        if (!serviceName) {
            Utils.showToast('Service name is required', 'warning');
            return;
        }

        DB.update(DB.GUEST_SERVICES, id, { serviceName, charge, notes });
        bootstrap.Modal.getInstance(document.getElementById('editAssignedServiceModal')).hide();
        Utils.showToast('Assigned service updated', 'success');
        LodgeApp.navigate('amenities');
    },

    deleteAssignedService(id) {
        if (!Utils.confirmAction('Remove this assigned service from the guest?')) return;
        DB.remove(DB.GUEST_SERVICES, id);
        Utils.showToast('Assigned service removed', 'info');
        LodgeApp.navigate('amenities');
    }
};
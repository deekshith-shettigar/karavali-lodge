/* ==========================================
   Room Service Module
   Karavali Lodge - Lodge Management
   ========================================== */
let _orderSaving = false;

const RoomServiceModule = {
    render() {
        const checkins = DB.getAll(DB.CHECKINS).filter(c => c.status === 'Checked-In');
        const orders = DB.getAll(DB.ROOM_SERVICE);
        const activeOrders = orders.filter(o => o.status === 'Pending' || o.status === 'Preparing');

        return `
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${orders.length}</div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cup-straw"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${activeOrders.length}</div>
                                <div class="stat-label">Active Orders</div>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${orders.filter(o => o.status === 'Delivered').length}</div>
                                <div class="stat-label">Delivered Today</div>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-all"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-value">${Utils.formatCurrency(orders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0))}</div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-currency-rupee"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-cart-plus me-2"></i>New Order</span>
                        </div>
                        <div class="card-body">
                            <form id="roomServiceForm">
                                <div class="mb-3">
                                    <label class="form-label">Room / Guest *</label>
                                    <select class="form-select" name="checkinId" required>
                                        <option value="">Select Guest</option>
                                        ${checkins.map(c => {
                                            const room = DB.getById(DB.ROOMS, c.roomId);
                                            return `<option value="${c.id}" data-room-id="${c.roomId}">Room ${room ? Utils.escapeHtml(room.roomNumber) : '?'} - ${Utils.escapeHtml(c.guestName)}</option>`;
                                        }).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Order Items *</label>
                                    <div id="orderItemsContainer">
                                        <div class="row g-2 mb-2 order-item">
                                            <div class="col-5">
                                                <input type="text" class="form-control form-control-sm" placeholder="Item name" maxlength="100">
                                            </div>
                                            <div class="col-2">
                                                <input type="number" class="form-control form-control-sm" placeholder="Qty" min="1" value="1">
                                            </div>
                                            <div class="col-3">
                                                <input type="number" class="form-control form-control-sm" placeholder="Price" min="0" step="0.01">
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.order-item').remove()">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="RoomServiceModule.addItemRow()">
                                        <i class="bi bi-plus"></i> Add Item
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Special Instructions</label>
                                    <textarea class="form-control" name="notes" rows="2" maxlength="300"></textarea>
                                </div>
                                <button type="button" class="btn btn-accent w-100" onclick="RoomServiceModule.placeOrder()">
                                    <i class="bi bi-send me-2"></i>Send to Kitchen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7" id="section-orders-list">
                    <div class="module-card">
                        <div class="card-header">
                            <span><i class="bi bi-list-ul me-2"></i>Orders</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table data-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Room</th>
                                            <th>Guest</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${orders.length === 0 ? '<tr><td colspan="7" class="text-center py-4 text-muted">No orders yet</td></tr>' :
                                        orders.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt)).map(o => {
                                            const room = DB.getById(DB.ROOMS, o.roomId);
                                            return `
                                            <tr>
                                                <td><strong>${Utils.escapeHtml(o.orderNo || o.id.substr(0,6).toUpperCase())}</strong></td>
                                                <td>${room ? Utils.escapeHtml(room.roomNumber) : '-'}</td>
                                                <td>${Utils.escapeHtml(o.guestName || '-')}</td>
                                                <td><small>${(o.items || []).map(i => Utils.escapeHtml(i.name)).join(', ')}</small></td>
                                                <td>${Utils.formatCurrency(o.total)}</td>
                                                <td>${Utils.statusBadge(o.status)}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        ${o.status === 'Pending' ? `<button class="btn btn-outline-warning" title="Mark Preparing" onclick="RoomServiceModule.updateStatus('${o.id}', 'Preparing')"><i class="bi bi-fire"></i></button>` : ''}
                                                        ${o.status === 'Preparing' ? `<button class="btn btn-outline-success" title="Mark Delivered" onclick="RoomServiceModule.updateStatus('${o.id}', 'Delivered')"><i class="bi bi-check2-all"></i></button>` : ''}
                                                        <button class="btn btn-outline-primary" title="Edit Order" onclick="RoomServiceModule.editOrder('${o.id}')"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-outline-danger" title="Delete Order" onclick="RoomServiceModule.deleteOrder('${o.id}')"><i class="bi bi-trash"></i></button>
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

            <!-- Edit Order Modal -->
            <div class="modal fade" id="editOrderModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editOrderForm">
                                <input type="hidden" id="editOrderId">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Order #</label>
                                    <input type="text" class="form-control" id="editOrderNo" readonly style="background:#f8f9fa">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select class="form-select" id="editOrderStatus">
                                        <option value="Pending">Pending</option>
                                        <option value="Preparing">Preparing</option>
                                        <option value="Delivered">Delivered</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Order Items</label>
                                    <div id="editOrderItemsContainer"></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="RoomServiceModule.addEditItemRow()">
                                        <i class="bi bi-plus"></i> Add Item
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Special Instructions</label>
                                    <textarea class="form-control" id="editOrderNotes" rows="2" maxlength="300"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-accent" onclick="RoomServiceModule.saveEditOrder()">
                                <i class="bi bi-check2 me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    addItemRow() {
        const container = document.getElementById('orderItemsContainer');
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 order-item';
        row.innerHTML = `
            <div class="col-5"><input type="text" class="form-control form-control-sm" placeholder="Item name" maxlength="100"></div>
            <div class="col-2"><input type="number" class="form-control form-control-sm" placeholder="Qty" min="1" value="1"></div>
            <div class="col-3"><input type="number" class="form-control form-control-sm" placeholder="Price" min="0" step="0.01"></div>
            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.order-item').remove()"><i class="bi bi-x"></i></button></div>
        `;
        container.appendChild(row);
    },

    addEditItemRow(name = '', qty = 1, price = '') {
        const container = document.getElementById('editOrderItemsContainer');
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 edit-order-item';
        row.innerHTML = `
            <div class="col-5"><input type="text" class="form-control form-control-sm" placeholder="Item name" value="${Utils.escapeHtml(name)}" maxlength="100"></div>
            <div class="col-2"><input type="number" class="form-control form-control-sm" placeholder="Qty" min="1" value="${qty}"></div>
            <div class="col-3"><input type="number" class="form-control form-control-sm" placeholder="Price" min="0" step="0.01" value="${price}"></div>
            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.edit-order-item').remove()"><i class="bi bi-x"></i></button></div>
        `;
        container.appendChild(row);
    },

    placeOrder() {
        if (_orderSaving) return;
        _orderSaving = true;

        const form = document.getElementById('roomServiceForm');
        const checkinId = form.elements.checkinId.value;
        if (!checkinId) {
            Utils.showToast('Please select a room/guest', 'warning');
            _orderSaving = false;
            return;
        }

        const checkin = DB.getById(DB.CHECKINS, checkinId);
        const items = [];
        let total = 0;

        document.querySelectorAll('.order-item').forEach(row => {
            const inputs = row.querySelectorAll('input');
            const name = inputs[0].value.trim();
            const qty = parseInt(inputs[1].value) || 1;
            const price = parseFloat(inputs[2].value) || 0;
            if (name && price > 0) {
                items.push({ name, qty, price, subtotal: qty * price });
                total += qty * price;
            }
        });

        if (items.length === 0) {
            Utils.showToast('Please add at least one item', 'warning');
            _orderSaving = false;
            return;
        }

        const order = {
            orderNo: 'RS-' + Date.now().toString().substr(-6),
            checkinId: checkinId,
            roomId: checkin ? checkin.roomId : null,
            guestName: checkin ? checkin.guestName : '',
            items: items,
            total: total,
            notes: form.elements.notes.value.trim(),
            status: 'Pending',
            orderTime: new Date().toISOString()
        };

        DB.add(DB.ROOM_SERVICE, order);
        _orderSaving = false;
        Utils.showToast('Order sent to kitchen!', 'success');
        LodgeApp.navigate('roomservice');
    },

    updateStatus(orderId, newStatus) {
        DB.update(DB.ROOM_SERVICE, orderId, { status: newStatus });
        Utils.showToast(`Order ${newStatus.toLowerCase()}`);
        LodgeApp.navigate('roomservice');
    },

    editOrder(orderId) {
        const order = DB.getById(DB.ROOM_SERVICE, orderId);
        if (!order) return;

        document.getElementById('editOrderId').value = order.id;
        document.getElementById('editOrderNo').value = order.orderNo || order.id.substr(0,6).toUpperCase();
        document.getElementById('editOrderStatus').value = order.status;
        document.getElementById('editOrderNotes').value = order.notes || '';

        // Populate items
        const container = document.getElementById('editOrderItemsContainer');
        container.innerHTML = '';
        (order.items || []).forEach(item => {
            RoomServiceModule.addEditItemRow(item.name, item.qty, item.price);
        });

        new bootstrap.Modal(document.getElementById('editOrderModal')).show();
    },

    saveEditOrder() {
        const orderId = document.getElementById('editOrderId').value;
        const status  = document.getElementById('editOrderStatus').value;
        const notes   = document.getElementById('editOrderNotes').value.trim();

        const items = [];
        let total = 0;
        document.querySelectorAll('.edit-order-item').forEach(row => {
            const inputs = row.querySelectorAll('input');
            const name  = inputs[0].value.trim();
            const qty   = parseInt(inputs[1].value) || 1;
            const price = parseFloat(inputs[2].value) || 0;
            if (name) {
                items.push({ name, qty, price, subtotal: qty * price });
                total += qty * price;
            }
        });

        if (items.length === 0) {
            Utils.showToast('Add at least one item', 'warning');
            return;
        }

        DB.update(DB.ROOM_SERVICE, orderId, { status, notes, items, total });
        bootstrap.Modal.getInstance(document.getElementById('editOrderModal')).hide();
        Utils.showToast('Order updated successfully', 'success');
        LodgeApp.navigate('roomservice');
    },

    deleteOrder(orderId) {
        if (!Utils.confirmAction('Delete this order? This cannot be undone.')) return;
        DB.remove(DB.ROOM_SERVICE, orderId);
        Utils.showToast('Order deleted', 'info');
        LodgeApp.navigate('roomservice');
    }
};
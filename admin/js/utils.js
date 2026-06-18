/* ==========================================
   Utility Functions
   ========================================== */
const Utils = {
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    },

    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
               d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    },

    formatCurrency(amount) {
        return '₹' + Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    daysBetween(date1, date2) {
        const d1 = new Date(date1);
        const d2 = new Date(date2);
        const diff = Math.abs(d2 - d1);
        return Math.ceil(diff / (1000 * 60 * 60 * 24));
    },

    today() {
        return new Date().toISOString().split('T')[0];
    },

    showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const icons = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        const toastId = 'toast_' + Date.now();
        const html = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${icons[type] || icons.info} me-2"></i>${this.escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        setTimeout(() => {
            const el = document.getElementById(toastId);
            if (el) el.remove();
        }, 3500);
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    statusBadge(status) {
        const cls = 'badge-' + (status || '').toLowerCase().replace(/[\s\/\-]/g, '');
        return `<span class="badge ${cls}">${this.escapeHtml(status)}</span>`;
    },

    confirmAction(message) {
        return confirm(message);
    },

    getFormData(formId) {
        const form = document.getElementById(formId);
        if (!form) return {};
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = typeof value === 'string' ? value.trim() : value;
        });
        return data;
    },

    populateForm(formId, data) {
        const form = document.getElementById(formId);
        if (!form || !data) return;
        Object.keys(data).forEach(key => {
            const el = form.elements[key];
            if (el) {
                if (el.type === 'checkbox') el.checked = !!data[key];
                else el.value = data[key] || '';
            }
        });
    },

    roomTypes: ['Single', 'Double', 'Deluxe', 'Suite', 'Family', 'Dormitory'],

    idProofTypes: ['Aadhaar', 'Passport', 'Driving License', 'Voter ID', 'PAN Card'],

    bookingTypes: ['Walk-in', 'Online', 'Advance Reservation'],

    roomStatuses: ['Available', 'Occupied', 'Reserved', 'Cleaning', 'Maintenance'],

    housekeepingStatuses: ['Dirty', 'Cleaning', 'Clean', 'Maintenance'],

    generateBillNo() {
        const d = new Date();
        return 'INV-' + d.getFullYear() + (d.getMonth()+1).toString().padStart(2,'0') +
               d.getDate().toString().padStart(2,'0') + '-' + Math.floor(Math.random()*9000+1000);
    }
};
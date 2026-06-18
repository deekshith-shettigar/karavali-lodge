/* ==========================================
   Karavali Lodge — db.js
   Universal localStorage + MySQL Direct Sync
   karavali_lodge/admin/js/db.js
   ========================================== */

const API_URL = 'http://localhost/karavali_lodge/admin/api.php';

const DB = {

    _getStore(key) {
        try {
            const raw = localStorage.getItem('kl_' + key);
            return raw ? JSON.parse(raw) : [];
        } catch { return []; }
    },

    _setStore(key, data) {
        localStorage.setItem('kl_' + key, JSON.stringify(data));
    },

    // ── Collections ──────────────────────────────────────────────────
    ROOMS:           'rooms',
    BOOKINGS:        'bookings',
    GUESTS:          'guests',
    CHECKINS:        'checkins',
    HOUSEKEEPING:    'housekeeping',
    ROOM_SERVICE:    'room_service',
    BILLS:           'bills',
    AMENITIES:       'amenities',
    GUEST_SERVICES:  'guest_services',
    NIGHT_AUDITS:    'night_audits',
    ID_PROOFS:       'id_proofs',
    SERVICE_MENU:    'service_menu',
    ONLINE_REQUESTS: 'online_requests',
    HK_STAFF:        'hk_staff',

    // ── Generic CRUD ─────────────────────────────────────────────────

    getAll(collection) {
        return this._getStore(collection);
    },

    getById(collection, id) {
        return this._getStore(collection).find(item => item.id === id) || null;
    },

    add(collection, item) {
        // Save to localStorage first (instant UI update)
        const items    = this._getStore(collection);
        item.id        = item.id        || this.generateId();
        item.createdAt = item.createdAt || new Date().toISOString();
        items.push(item);
        this._setStore(collection, items);
        // Save to MySQL immediately
        DB._save(collection, item);
        return item;
    },

    update(collection, id, updates) {
        // Update localStorage first (instant UI update)
        const items = this._getStore(collection);
        const idx   = items.findIndex(item => item.id === id);
        if (idx === -1) return null;
        items[idx] = { ...items[idx], ...updates, updatedAt: new Date().toISOString() };
        this._setStore(collection, items);
        // Save to MySQL immediately
        DB._save(collection, items[idx]);
        return items[idx];
    },

    remove(collection, id) {
        const items    = this._getStore(collection);
        const filtered = items.filter(item => item.id !== id);
        const deleted  = filtered.length < items.length;
        this._setStore(collection, filtered);
        if (deleted) DB._delete(collection, id);
        return deleted;
    },

    query(collection, filterFn) {
        return this._getStore(collection).filter(filterFn);
    },

    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2, 6);
    },

    clearAll() {
        Object.keys(localStorage)
              .filter(k => k.startsWith('kl_'))
              .forEach(k => localStorage.removeItem(k));
    },

    // ── MySQL Direct Sync ─────────────────────────────────────────────

    // Save any record directly to MySQL
    // credentials:'include' is required so the browser sends the admin
    // session cookie to api.php — without it every request returns 401.
    async _save(collection, record) {
        try {
            const res = await fetch(API_URL + '?action=save', {
                method:      'POST',
                credentials: 'include',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify({ collection, record })
            });
            if (res.status === 401) {
                DB._setSyncStatus('Session expired — please log in again', 'danger');
                console.warn('Save failed 401: session expired for collection:', collection);
            }
        } catch(e) {
            console.warn('Save error:', e);
        }
    },

    // Delete any record directly from MySQL
    async _delete(collection, id) {
        try {
            const res  = await fetch(API_URL + '?action=delete', {
                method:      'POST',
                credentials: 'include',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify({ collection, id })
            });
            const json = await res.json();
            if (json.error) {
                if (typeof Utils !== 'undefined') {
                    Utils.showToast(json.error, 'danger');
                } else {
                    alert(json.error);
                }
            }
        } catch(e) {
            console.warn('Delete error:', e);
            if (typeof Utils !== 'undefined') {
                Utils.showToast('Network error — record may not be deleted from server. Please sync.', 'warning');
            }
        }
    },

    // Pull fresh data from MySQL → overwrite localStorage
    async pullFromMySQL() {
        try {
            DB._setSyncStatus('Syncing...', 'info');
            // credentials:'include' sends the session cookie so api.php
            // recognises the logged-in admin and returns data instead of 401.
            const res  = await fetch(API_URL + '?action=pull', {
                credentials: 'include'
            });
            const json = await res.json();

            if (!json.success) {
                DB._setSyncStatus('Sync failed: ' + (json.error || 'Unknown'), 'danger');
                return false;
            }

            const d = json.data;
            if (d.rooms          !== undefined) localStorage.setItem('kl_rooms',          JSON.stringify(d.rooms));
            if (d.guests         !== undefined) localStorage.setItem('kl_guests',         JSON.stringify(d.guests));
            if (d.checkins       !== undefined) localStorage.setItem('kl_checkins',       JSON.stringify(d.checkins));
            if (d.bills          !== undefined) localStorage.setItem('kl_bills',          JSON.stringify(d.bills));
            if (d.housekeeping   !== undefined) localStorage.setItem('kl_housekeeping',   JSON.stringify(d.housekeeping));
            if (d.service_menu   !== undefined) localStorage.setItem('kl_service_menu',   JSON.stringify(d.service_menu));
            if (d.guest_services !== undefined) localStorage.setItem('kl_guest_services', JSON.stringify(d.guest_services));
            if (d.room_service   !== undefined) localStorage.setItem('kl_room_service',   JSON.stringify(d.room_service));
            if (d.night_audits   !== undefined) localStorage.setItem('kl_night_audits',   JSON.stringify(d.night_audits));
            if (d.id_proofs      !== undefined) localStorage.setItem('kl_id_proofs',      JSON.stringify(d.id_proofs));
            if (d.hk_staff       !== undefined) localStorage.setItem('kl_hk_staff',       JSON.stringify(d.hk_staff));

            localStorage.setItem('kl_bookings',        JSON.stringify(d.bookings        || []));
            localStorage.setItem('kl_online_requests', JSON.stringify(d.online_requests || []));

            DB._lastSync = new Date();
            DB._setSyncStatus('✓ Synced at ' + DB._lastSync.toLocaleTimeString(), 'success');
            DB._updateRequestBadge();
            return true;

        } catch (err) {
            console.error('Pull error:', err);
            DB._setSyncStatus('⚠ Cannot connect to database', 'warning');
            return false;
        }
    },

    // pushToMySQL kept for compatibility (Sync Now button)
    async pushToMySQL() {
        return true;
    },

    async pushNow() {
        return true;
    },

    // Confirm online booking request
    async confirmOnlineRequest(requestId) {
        try {
            const numericId = String(requestId).replace('req_', '');
            const res  = await fetch(API_URL + '?action=confirm_request', {
                method:      'POST',
                credentials: 'include',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify({ request_id: numericId })
            });
            const json = await res.json();
            if (json.success) {
                await DB.pullFromMySQL();
                return json;
            }
            return null;
        } catch (err) {
            console.error('Confirm error:', err);
            return null;
        }
    },

    _lastSync: null,

    _setSyncStatus(msg, type) {
        const el = document.getElementById('syncStatus');
        if (!el) return;
        const colors = { success:'#28a745', info:'#17a2b8', warning:'#e67e22', danger:'#dc3545' };
        el.textContent = msg;
        el.style.color = colors[type] || '#6c757d';
    },

    _updateRequestBadge() {
        const pending = DB._getStore('online_requests')
                          .filter(b => b.status === 'Pending');
        const count = pending.length;

        const badge = document.getElementById('reqBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('show', count > 0);
        }

        const topBadge = document.getElementById('reqTopbarBadge');
        if (topBadge) {
            topBadge.textContent = count;
            topBadge.style.display = count > 0 ? 'block' : 'none';
        }
        const bell = document.getElementById('reqBellBtn');
        if (bell) {
            bell.style.borderColor = count > 0 ? '#dc3545' : '';
            bell.style.color       = count > 0 ? '#dc3545' : '';
        }
    },

    startAutoSync(intervalMs = 60000) {
        DB.pullFromMySQL();
        setInterval(() => DB.pullFromMySQL(), intervalMs);
    }
};
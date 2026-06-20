/* ==========================================
   Main Application Orchestrator
   Karavali Lodge - Lodge Management
   ========================================== */
const LodgeApp = {
    currentModule: 'dashboard',

    modules: {
        dashboard: { title: 'Dashboard', module: DashboardModule },
        rooms: { title: 'Room Management', module: RoomModule },
        booking: { title: 'Booking / Reservation', module: BookingModule },
        guests: { title: 'Guest Management', module: GuestModule },
        checkin: { title: 'Check-In / Check-Out', module: CheckInModule },
        housekeeping: { title: 'Housekeeping Management', module: HousekeepingModule },
        roomservice: { title: 'Room Service', module: RoomServiceModule },
        billing: { title: 'Billing', module: BillingModule },
        amenities: { title: 'Amenity / Services', module: AmenityModule },
        calendar: { title: 'Availability Calendar', module: CalendarModule },
        nightaudit: { title: 'Night Audit', module: NightAuditModule },
        reports: { title: 'Lodge Reports', module: ReportsModule },
        idverify: { title: 'ID Verification', module: IDVerifyModule }
    },

    init() {
        this.setupNavigation();
        this.setupSidebar();
        this.updateDateTime();
        setInterval(() => this.updateDateTime(), 60000);

        // ── Restore last visited page on refresh ──────────────────
        const saved = sessionStorage.getItem('kl_active_module');

        if (saved === 'online_requests') {
            // Special module — handled by index.php click handler
            // Use a short delay so index.php's DOMContentLoaded listeners are wired first
            setTimeout(() => {
                const link = document.getElementById('onlineRequestsLink');
                if (link) link.click();
            }, 50);
        } else if (saved === 'messages') {
            // Special module — handled by index.php click handler
            setTimeout(() => {
                const link = document.getElementById('messagesLink');
                if (link) link.click();
            }, 50);
        } else {
            // Regular module — navigate directly
            this.navigate(saved && this.modules[saved] ? saved : 'dashboard');
        }

        this.loadAdminInfo();
    },

    setupNavigation() {
        const sidebar = document.querySelector('.sidebar-nav');
        if (!sidebar) return;

        sidebar.addEventListener('click', (e) => {
            const subLink = e.target.closest('.nav-sub-link');
            const toggleLink = e.target.closest('.dropdown-toggle-link');
            const topNavLink = e.target.closest('.sidebar-nav > .nav-link:not(.dropdown-toggle-link)');

            // --- Sub-link clicked (Room List, Add Room, etc.) ---
            if (subLink) {
                e.preventDefault();
                e.stopPropagation();
                const module = subLink.getAttribute('data-module');
                const action = subLink.getAttribute('data-action');
                if (module) {
                    this.navigate(module);
                    if (action) {
                        setTimeout(() => this.handleSubAction(module, action), 100);
                    }
                }
                // Clear all active states
                sidebar.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                sidebar.querySelectorAll('.nav-sub-link').forEach(l => l.classList.remove('active'));
                // Highlight parent dropdown toggle and the clicked sub-link
                const parentDropdown = subLink.closest('.nav-item-dropdown');
                if (parentDropdown) {
                    const toggle = parentDropdown.querySelector('.dropdown-toggle-link');
                    if (toggle) toggle.classList.add('active');
                }
                subLink.classList.add('active');
                this.closeSidebar();
                return;
            }

            // --- Dropdown toggle clicked (Room Management, Booking, etc.) ---
            if (toggleLink) {
                e.preventDefault();
                const parent = toggleLink.closest('.nav-item-dropdown');
                // Close all other dropdowns
                document.querySelectorAll('.nav-item-dropdown.open').forEach(el => {
                    if (el !== parent) el.classList.remove('open');
                });
                // Toggle current dropdown
                parent.classList.toggle('open');
                return;
            }

            // --- Top-level nav link clicked (Dashboard, Calendar, etc.) ---
            if (topNavLink) {
                e.preventDefault();
                document.querySelectorAll('.nav-item-dropdown.open').forEach(el => el.classList.remove('open'));
                sidebar.querySelectorAll('.nav-sub-link').forEach(l => l.classList.remove('active'));
                sidebar.querySelectorAll('.dropdown-toggle-link').forEach(l => l.classList.remove('active'));
                const module = topNavLink.getAttribute('data-module');
                if (module) this.navigate(module);
                this.closeSidebar();
                return;
            }
        });
    },

    handleSubAction(module, action) {
        const actions = {
            'rooms:addRoom': () => RoomModule.showForm(),
            'booking:newBooking': () => BookingModule.showForm(),
            'guests:addGuest': () => GuestModule.showForm(),
            'checkin:checkouts': () => {
                const el = document.getElementById('section-checkedin-guests');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            'housekeeping:tasks': () => {
                const el = document.getElementById('section-active-tasks');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            'roomservice:orders': () => {
                const el = document.getElementById('section-orders-list');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            'billing:payments': () => {
                const filter = document.getElementById('billStatusFilter');
                if (filter) {
                    filter.value = 'Unpaid';
                    BillingModule.filterBills();
                }
            },
            'amenities:assign': () => {
                const el = document.getElementById('section-assign-service');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            'reports:occupancy': () => ReportsModule.showReport('occupancy'),
            'reports:revenue': () => ReportsModule.showReport('revenue'),
            'reports:guest': () => ReportsModule.showReport('guest'),
            'reports:services': () => ReportsModule.showReport('services')
        };
        const key = module + ':' + action;
        if (actions[key]) actions[key]();
    },

    setupSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('sidebarClose');
        const toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;

        const isMobile = () => window.innerWidth <= 992;

        const openSidebarMobile = () => {
            sidebar.classList.add('show');
            overlay.classList.add('active');
        };

        const closeSidebarMobile = () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('active');
        };

        const toggleDesktop = () => {
            const collapsed = sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded', collapsed);
            if (toggleIcon) {
                toggleIcon.className = collapsed ? 'bi bi-layout-sidebar-inset fs-5' : 'bi bi-list fs-5';
            }
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (isMobile()) {
                    if (sidebar.classList.contains('show')) {
                        closeSidebarMobile();
                    } else {
                        openSidebarMobile();
                    }
                } else {
                    toggleDesktop();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebarMobile);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebarMobile);
        }

        // On resize, clean up states if crossing the breakpoint
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('show');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                if (toggleIcon) toggleIcon.className = 'bi bi-list fs-5';
            }
        });
    },

    closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (window.innerWidth <= 992) {
            sidebar.classList.remove('show');
            overlay.classList.remove('active');
        }
    },

    navigate(moduleName) {
        if (!this.modules[moduleName]) return;

        this.currentModule = moduleName;

        // ── Remember this page so refresh restores it ─────────────
        sessionStorage.setItem('kl_active_module', moduleName);
        const config = this.modules[moduleName];

        // Update nav active state — top-level simple links
        document.querySelectorAll('.sidebar-nav > .nav-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-module') === moduleName);
        });

        // Update nav active state — dropdown toggle links
        document.querySelectorAll('.dropdown-toggle-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-module') === moduleName);
        });

        // Update page title
        document.getElementById('pageTitle').textContent = config.title;

        // Render module content
        const content = document.getElementById('contentArea');
        content.innerHTML = config.module.render();

        // Run module init if exists
        if (typeof config.module.init === 'function') {
            config.module.init();
        }

        // Scroll to top
        window.scrollTo(0, 0);
    },

    updateDateTime() {
        const el = document.getElementById('currentDateTime');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleDateString('en-IN', {
                weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
            }) + ' | ' + now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        }
    },

    logout() {
        if (!Utils.confirmAction('Are you sure you want to logout?')) return;
        // Clear all hotel data from localStorage before redirecting —
        // prevents the next person on a shared computer from seeing
        // rooms, guests, and bookings from this admin session.
        DB.clearAll();
        window.location.href = window.location.origin + '/user/pages/logout.php';
    },

    async loadAdminInfo() {
        try {
            const res  = await fetch('api.php?action=get_admin');
            const data = await res.json();
            if (data.success) {
                const nameEl   = document.getElementById('adminInfoName');
                const mobileEl = document.getElementById('adminInfoMobile');
                const labelEl  = document.getElementById('adminDropdownLabel');
                if (nameEl)   nameEl.textContent   = data.name   || 'Admin';
                if (mobileEl) mobileEl.textContent = data.mobile || '';
                if (labelEl)  labelEl.textContent  = (data.name || 'Admin').split(' ')[0];
            }
        } catch (e) { /* silently fail */ }
    },

    resetData() {
        if (!Utils.confirmAction('This will delete ALL data. Are you sure?')) return;
        DB.clearAll();
        Utils.showToast('All data has been reset', 'info');
        this.navigate(this.currentModule);
    },

    loadSampleData() {
        if (!Utils.confirmAction('Load sample data? This will add rooms, guests, and bookings.')) return;

        // Sample Rooms
        const sampleRooms = [
            { roomNumber: '101', roomType: 'Single', floor: '1', price: 1200, capacity: 1, status: 'Available', amenities: 'WiFi, TV, AC' },
            { roomNumber: '102', roomType: 'Single', floor: '1', price: 1200, capacity: 1, status: 'Available', amenities: 'WiFi, TV, AC' },
            { roomNumber: '103', roomType: 'Double', floor: '1', price: 2000, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar' },
            { roomNumber: '104', roomType: 'Double', floor: '1', price: 2000, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar' },
            { roomNumber: '201', roomType: 'Deluxe', floor: '2', price: 3500, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar, Balcony' },
            { roomNumber: '202', roomType: 'Deluxe', floor: '2', price: 3500, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar, Balcony' },
            { roomNumber: '203', roomType: 'Suite', floor: '2', price: 5500, capacity: 3, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar, Balcony, Living Room' },
            { roomNumber: '204', roomType: 'Family', floor: '2', price: 4500, capacity: 4, status: 'Available', amenities: 'WiFi, TV, AC, Extra Beds' },
            { roomNumber: '301', roomType: 'Suite', floor: '3', price: 6000, capacity: 3, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar, Jacuzzi, Lake View' },
            { roomNumber: '302', roomType: 'Deluxe', floor: '3', price: 3500, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC, Mini-bar' },
            { roomNumber: '303', roomType: 'Double', floor: '3', price: 2200, capacity: 2, status: 'Available', amenities: 'WiFi, TV, AC' },
            { roomNumber: '304', roomType: 'Single', floor: '3', price: 1500, capacity: 1, status: 'Available', amenities: 'WiFi, TV, AC' },
            { roomNumber: 'D01', roomType: 'Dormitory', floor: 'G', price: 600, capacity: 6, status: 'Available', amenities: 'WiFi, Fan' },
            { roomNumber: 'D02', roomType: 'Dormitory', floor: 'G', price: 600, capacity: 6, status: 'Available', amenities: 'WiFi, Fan' }
        ];

        // Only add if rooms are empty
        if (DB.getAll(DB.ROOMS).length === 0) {
            sampleRooms.forEach(r => DB.add(DB.ROOMS, r));
        }

        // Sample Guests
        const sampleGuests = [
            { name: 'Rajesh Kumar', mobile: '9876543210', email: 'rajesh@email.com', nationality: 'Indian', address: '123 MG Road, Bangalore' },
            { name: 'Priya Sharma', mobile: '9876543211', email: 'priya@email.com', nationality: 'Indian', address: '456 Brigade Road, Mysore' },
            { name: 'Amit Patel', mobile: '9876543212', email: 'amit@email.com', nationality: 'Indian', address: '789 Ring Road, Hubli' },
            { name: 'Sneha Reddy', mobile: '9876543213', email: 'sneha@email.com', nationality: 'Indian', address: '321 Lake Road, Mangalore' },
            { name: 'John Smith', mobile: '9876543214', email: 'john@email.com', nationality: 'British', address: '10 High Street, London' }
        ];

        if (DB.getAll(DB.GUESTS).length === 0) {
            sampleGuests.forEach(g => DB.add(DB.GUESTS, g));
        }

        // Sample Bookings
        const rooms = DB.getAll(DB.ROOMS);
        const guests = DB.getAll(DB.GUESTS);
        if (DB.getAll(DB.BOOKINGS).length === 0 && rooms.length > 0 && guests.length > 0) {
            const today = new Date();
            const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
            const dayAfter = new Date(today); dayAfter.setDate(today.getDate() + 2);
            const nextWeek = new Date(today); nextWeek.setDate(today.getDate() + 7);

            const sampleBookings = [
                {
                    bookingNo: 'BK-000001',
                    guestName: guests[0].name, mobile: guests[0].mobile,
                    roomId: rooms[0].id, roomType: rooms[0].roomType,
                    bookingType: 'Walk-in', checkIn: Utils.today(),
                    checkOut: tomorrow.toISOString().split('T')[0],
                    numGuests: 1, status: 'Confirmed', advance: 500
                },
                {
                    bookingNo: 'BK-000002',
                    guestName: guests[1].name, mobile: guests[1].mobile,
                    roomId: rooms[4].id, roomType: rooms[4].roomType,
                    bookingType: 'Online', checkIn: Utils.today(),
                    checkOut: dayAfter.toISOString().split('T')[0],
                    numGuests: 2, status: 'Confirmed', advance: 1000
                },
                {
                    bookingNo: 'BK-000003',
                    guestName: guests[2].name, mobile: guests[2].mobile,
                    roomId: rooms[6].id, roomType: rooms[6].roomType,
                    bookingType: 'Advance Reservation',
                    checkIn: nextWeek.toISOString().split('T')[0],
                    checkOut: new Date(nextWeek.getTime() + 3 * 86400000).toISOString().split('T')[0],
                    numGuests: 2, status: 'Pending', advance: 2000
                }
            ];
            sampleBookings.forEach(b => DB.add(DB.BOOKINGS, b));

            // Reserve rooms for bookings
            DB.update(DB.ROOMS, rooms[0].id, { status: 'Reserved' });
            DB.update(DB.ROOMS, rooms[4].id, { status: 'Reserved' });
            DB.update(DB.ROOMS, rooms[6].id, { status: 'Reserved' });
        }

        Utils.showToast('Sample data loaded! 14 rooms, 5 guests, and 3 bookings added.', 'success');
        this.navigate(this.currentModule);
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    LodgeApp.init();
});
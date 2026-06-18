/* =============================================
   Karavali Lodge - Main JS
   ============================================= */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================
    // Navbar scroll effect
    // =========================================
    const navbar = document.getElementById('mainNav');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 60) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // =========================================
    // Scroll to Top Button
    // =========================================
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        });

        scrollBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // =========================================
    // Animate on scroll
    // =========================================
    function animateOnScroll() {
        const elements = document.querySelectorAll('[data-animate]');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const animation = el.getAttribute('data-animate') || 'fadeInUp';
                    el.classList.add('animate-' + animation);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        elements.forEach(el => observer.observe(el));
    }

    animateOnScroll();

    // =========================================
    // Auto-dismiss alerts
    // =========================================
    const alerts = document.querySelectorAll('.alert-hotel[data-auto-dismiss]');
    alerts.forEach(alert => {
        const delay = parseInt(alert.getAttribute('data-auto-dismiss')) || 4000;
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.4s ease';
            setTimeout(() => alert.remove(), 400);
        }, delay);
    });

    // =========================================
    // Room filter on rooms.php
    // =========================================
    const typeFilter    = document.getElementById('filterType');
    const priceFilter   = document.getElementById('filterPrice');
    const sortFilter    = document.getElementById('filterSort');
    const roomGrid      = document.getElementById('roomGrid');

    if (typeFilter || priceFilter) {
        [typeFilter, priceFilter, sortFilter].forEach(el => {
            if (el) el.addEventListener('change', filterRooms);
        });
    }

    function filterRooms() {
        const type  = typeFilter  ? typeFilter.value  : '';
        const price = priceFilter ? priceFilter.value : '';
        const cards = document.querySelectorAll('.room-card-wrapper');

        cards.forEach(card => {
            const cardType  = card.getAttribute('data-type')  || '';
            const cardPrice = parseFloat(card.getAttribute('data-price') || 0);
            let show = true;

            if (type  && cardType !== type)  show = false;
            if (price) {
                const [min, max] = price.split('-').map(Number);
                if (cardPrice < min || (max && cardPrice > max)) show = false;
            }

            card.style.display = show ? '' : 'none';
        });

        // Sort
        if (sortFilter && roomGrid) {
            const wrappers = Array.from(document.querySelectorAll('.room-card-wrapper:not([style*="none"])'));
            wrappers.sort((a, b) => {
                const pa = parseFloat(a.getAttribute('data-price') || 0);
                const pb = parseFloat(b.getAttribute('data-price') || 0);
                return sortFilter.value === 'price_asc' ? pa - pb : pb - pa;
            });
            wrappers.forEach(w => roomGrid.appendChild(w));
        }
    }

    // =========================================
    // Booking Form: Date validation
    // =========================================
    const checkInInput  = document.getElementById('check_in');
    const checkOutInput = document.getElementById('check_out');

    if (checkInInput && checkOutInput) {
        const today = new Date().toISOString().split('T')[0];
        checkInInput.min  = today;
        checkOutInput.min = today;

        checkInInput.addEventListener('change', function () {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOutInput.min = nextDay.toISOString().split('T')[0];
            if (checkOutInput.value && checkOutInput.value <= this.value) {
                checkOutInput.value = nextDay.toISOString().split('T')[0];
            }
            updateBookingSummary();
        });

        checkOutInput.addEventListener('change', updateBookingSummary);
    }

    // =========================================
    // Booking Summary Calculator
    // =========================================
    function updateBookingSummary() {
        const ci = document.getElementById('check_in');
        const co = document.getElementById('check_out');
        const roomSelect = document.getElementById('room_id');
        const summarySection = document.getElementById('bookingSummary');

        if (!ci || !co || !roomSelect || !summarySection) return;

        const checkIn  = new Date(ci.value);
        const checkOut = new Date(co.value);
        const roomOpt  = roomSelect.options[roomSelect.selectedIndex];
        const pricePerNight = parseFloat(roomOpt ? roomOpt.getAttribute('data-price') || 0 : 0);

        if (!ci.value || !co.value || checkOut <= checkIn) {
            summarySection.style.display = 'none';
            return;
        }

        const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
        const roomCharges = nights * pricePerNight;
        const gst = roomCharges * 0.12;
        const total = roomCharges + gst;

        document.getElementById('summNights')      && (document.getElementById('summNights').textContent = nights);
        document.getElementById('summRoomCharges') && (document.getElementById('summRoomCharges').textContent = formatCurrency(roomCharges));
        document.getElementById('summGST')         && (document.getElementById('summGST').textContent = formatCurrency(gst));
        document.getElementById('summTotal')       && (document.getElementById('summTotal').textContent = formatCurrency(total));
        document.getElementById('summRoomType')    && (document.getElementById('summRoomType').textContent = roomOpt ? roomOpt.text.split('(')[0].trim() : '');
        document.getElementById('summCheckIn')     && (document.getElementById('summCheckIn').textContent = formatDate(ci.value));
        document.getElementById('summCheckOut')    && (document.getElementById('summCheckOut').textContent = formatDate(co.value));

        summarySection.style.display = 'block';
    }

    const roomSelectEl = document.getElementById('room_id');
    if (roomSelectEl) roomSelectEl.addEventListener('change', updateBookingSummary);

    // Initial calc
    updateBookingSummary();

    // =========================================
    // Room select card highlight
    // =========================================
    document.querySelectorAll('.room-select-card').forEach(card => {
        const radio = card.querySelector('input[type="radio"]');
        if (radio) {
            card.addEventListener('click', function () {
                document.querySelectorAll('.room-select-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                radio.checked = true;
                updateBookingSummary();
            });
        }
    });

    // =========================================
    // Helpers
    // =========================================
    function formatCurrency(amount) {
        return '₹' + Number(amount).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('en-IN', {
            day: '2-digit', month: 'short', year: 'numeric'
        });
    }

    // =========================================
    // Gallery lightbox (simple)
    // =========================================
    const galleryItems = document.querySelectorAll('.gallery-item');
    galleryItems.forEach(item => {
        item.addEventListener('click', function () {
            const img = this.querySelector('img');
            if (!img) return;
            const modal = document.createElement('div');
            modal.style.cssText = `
                position:fixed;inset:0;background:rgba(0,0,0,0.92);
                z-index:9999;display:flex;align-items:center;justify-content:center;
                cursor:pointer;animation:fadeIn .3s ease;
            `;
            modal.innerHTML = `
                <button style="position:absolute;top:20px;right:24px;background:rgba(255,255,255,0.1);
                    border:1px solid rgba(255,255,255,0.2);color:#fff;border-radius:8px;
                    width:40px;height:40px;font-size:1.2rem;cursor:pointer;display:flex;
                    align-items:center;justify-content:center;">✕</button>
                <img src="${img.src}" style="max-width:90vw;max-height:85vh;object-fit:contain;
                    border-radius:12px;box-shadow:0 24px 60px rgba(0,0,0,0.5);">
            `;
            document.body.appendChild(modal);
            modal.addEventListener('click', () => modal.remove());
        });
    });

    // =========================================
    // Booking Confirmation: countdown redirect
    // =========================================
    const countdown = document.getElementById('redirectCountdown');
    if (countdown) {
        let count = parseInt(countdown.textContent);
        const interval = setInterval(() => {
            count--;
            countdown.textContent = count;
            if (count <= 0) {
                clearInterval(interval);
                const redirectUrl = countdown.getAttribute('data-url');
                if (redirectUrl) window.location.href = redirectUrl;
            }
        }, 1000);
    }

    // =========================================
    // Mobile: close nav on link click
    // =========================================
    document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)').forEach(link => {
        link.addEventListener('click', () => {
            const navbarCollapse = document.getElementById('navbarMain');
            if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse) bsCollapse.hide();
            }
        });
    });
});

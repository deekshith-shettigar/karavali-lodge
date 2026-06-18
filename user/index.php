<?php
$pageTitle = "Karavali Lodge - Premium Lodge & Stay";
$pageDesc  = "Experience luxury and comfort at Karavali Lodge. Book your stay in premium rooms with world-class amenities.";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
?>
<?php
$db = getDB();
$stmt = $db->query("SELECT * FROM rooms WHERE status NOT IN ('Maintenance','Out of Order') ORDER BY price DESC LIMIT 6");
$featuredRooms = $stmt->fetchAll();
$roomImages = [
    'Single'    => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80',
    'Double'    => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=600&q=80',
    'Deluxe'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&q=80',
    'Suite'     => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=600&q=80',
    'Family'    => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=600&q=80',
    'Dormitory' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=600&q=80',
];
?>

<!-- Flatpickr CSS — hotel theme matching room-booking.php -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.flatpickr-calendar{font-family:'Jost',sans-serif!important;box-shadow:0 20px 60px rgba(59,26,10,0.25)!important;border-radius:18px!important;border:1px solid #E8D9C0!important;width:320px!important;overflow:hidden!important;padding:0!important;background:#fff!important;}
.flatpickr-months{background:linear-gradient(135deg,#2A1007,#4a1e08)!important;border-radius:18px 18px 0 0!important;height:56px!important;display:flex!important;align-items:center!important;position:relative!important;}
.flatpickr-month{height:56px!important;color:#fff!important;fill:#fff!important;display:flex!important;align-items:center!important;justify-content:center!important;}
.flatpickr-current-month{display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;padding:0!important;height:56px!important;font-size:1rem!important;}
.flatpickr-current-month .flatpickr-monthDropdown-months{-webkit-appearance:none!important;appearance:none!important;background:rgba(255,255,255,0.12)!important;border:1px solid rgba(196,148,58,0.5)!important;border-radius:8px!important;color:#fff!important;font-family:'Playfair Display',Georgia,serif!important;font-size:1rem!important;font-weight:700!important;font-style:italic!important;cursor:pointer!important;padding:4px 28px 4px 10px!important;outline:none!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23C4943A' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right 8px center!important;background-size:16px!important;transition:all 0.2s!important;}
.flatpickr-current-month .flatpickr-monthDropdown-months:hover{background-color:rgba(196,148,58,0.25)!important;border-color:#C4943A!important;}
.flatpickr-current-month .flatpickr-monthDropdown-months option{background:#3B1A0A!important;color:#fff!important;font-style:normal!important;font-family:'Jost',sans-serif!important;}
.flatpickr-current-month .numInputWrapper{background:rgba(255,255,255,0.12)!important;border:1px solid rgba(196,148,58,0.5)!important;border-radius:8px!important;padding:0!important;width:72px!important;transition:all 0.2s!important;}
.flatpickr-current-month .numInputWrapper:hover{background:rgba(196,148,58,0.25)!important;border-color:#C4943A!important;}
.flatpickr-current-month input.cur-year{color:#D4AD5E!important;font-size:0.95rem!important;font-weight:700!important;font-family:'Jost',sans-serif!important;background:transparent!important;border:none!important;outline:none!important;padding:4px 6px 4px 10px!important;width:100%!important;cursor:pointer!important;}
.flatpickr-current-month .numInputWrapper span{display:flex!important;align-items:center!important;justify-content:center!important;border:none!important;right:0!important;width:18px!important;opacity:1!important;}
.flatpickr-current-month .numInputWrapper span.arrowUp{top:0!important;height:50%!important;border-bottom:1px solid rgba(196,148,58,0.3)!important;}
.flatpickr-current-month .numInputWrapper span.arrowDown{top:50%!important;height:50%!important;}
.flatpickr-current-month .numInputWrapper span::after{border-left-color:#C4943A!important;border-right-color:#C4943A!important;}
.flatpickr-current-month .numInputWrapper span.arrowUp::after{border-bottom-color:#C4943A!important;}
.flatpickr-current-month .numInputWrapper span.arrowDown::after{border-top-color:#C4943A!important;}
.flatpickr-prev-month,.flatpickr-next-month{fill:#C4943A!important;color:#C4943A!important;height:56px!important;padding:0 14px!important;display:flex!important;align-items:center!important;top:0!important;transition:background 0.2s!important;}
.flatpickr-prev-month:hover,.flatpickr-next-month:hover{background:rgba(196,148,58,0.2)!important;}
.flatpickr-prev-month svg,.flatpickr-next-month svg{width:14px!important;height:14px!important;fill:#C4943A!important;}
.flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg{fill:#D4AD5E!important;}
.flatpickr-weekdays{background:#FBF7F2!important;border-bottom:1px solid #EFE5D8!important;height:38px!important;margin-top:20px!important;}
.flatpickr-weekday{color:#C4943A!important;font-weight:700!important;font-size:0.7rem!important;letter-spacing:0.8px!important;text-transform:uppercase!important;}
.flatpickr-innerContainer{background:#fff!important;}
.flatpickr-days{border:none!important;}
.dayContainer{padding:8px 8px 10px!important;width:100%!important;min-width:100%!important;max-width:100%!important;}
.flatpickr-day{border-radius:10px!important;color:#3B1A0A!important;font-size:0.84rem!important;font-weight:500!important;height:36px!important;line-height:36px!important;max-width:36px!important;border:1.5px solid transparent!important;transition:all 0.15s!important;margin:2px!important;}
.flatpickr-day:hover{background:#FBF0DC!important;border-color:#E8D9C0!important;}
.flatpickr-day.today{border-color:#C4943A!important;background:#FFF8EC!important;font-weight:800!important;color:#8B6914!important;}
.flatpickr-day.selected,.flatpickr-day.selected:hover{background:linear-gradient(135deg,#C4943A,#D4AD5E)!important;border-color:#C4943A!important;color:#fff!important;font-weight:700!important;box-shadow:0 3px 12px rgba(196,148,58,0.4)!important;}
.flatpickr-day.disabled,.flatpickr-day.prevMonthDay,.flatpickr-day.nextMonthDay{color:#D5C5B5!important;background:transparent!important;border-color:transparent!important;}
.flatpickr-day.flatpickr-disabled,.flatpickr-day.flatpickr-disabled:hover{color:#E0D5C8!important;cursor:not-allowed!important;}
/* Make date inputs match hotel style */
#quick_checkin,#quick_checkout{cursor:pointer!important;}
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-bg-img" id="heroBg"></div>
    <div class="hero-bg"></div>
    <div class="hero-pattern"></div>
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="hero-badge animate-fadeInUp delay-1"><i class="bi bi-gem"></i> Premium Hospitality Since 2010</div>
                <h1 class="hero-title animate-fadeInUp delay-2">Welcome to<br><em class="accent-word">Karavali Lodge</em></h1>
                <p class="hero-subtitle animate-fadeInUp delay-3">Where every room tells a story of comfort, elegance, and the warmth of genuine hospitality.</p>
                <div class="hero-actions animate-fadeInUp delay-4">
                    <a href="<?= SITE_URL ?>/pages/booking.php" class="btn-hero-primary"><i class="bi bi-calendar-plus me-2"></i>Reserve Your Stay</a>
                    <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-hero-outline"><i class="bi bi-door-open me-2"></i>Explore Rooms</a>
                </div>
                <div class="hero-stats animate-fadeInUp delay-5">
                    <div class="hero-stat"><div class="hero-stat-num">14+</div><div class="hero-stat-label">Rooms</div></div>
                    <div class="hero-stat"><div class="hero-stat-num">500+</div><div class="hero-stat-label">Happy Guests</div></div>
                    <div class="hero-stat"><div class="hero-stat-num">15</div><div class="hero-stat-label">Years Serving</div></div>
                    <div class="hero-stat"><div class="hero-stat-num">4.8★</div><div class="hero-stat-label">Rating</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Booking Bar -->
<section class="pb-5" style="background:var(--cream)">
    <div class="container">
        <div class="booking-bar">
            <div class="booking-bar-title"><i class="bi bi-calendar3 me-2"></i>Check Availability</div>
            <form action="<?= SITE_URL ?>/pages/booking.php" method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <div class="booking-field">
                        <label for="quick_checkin">Check-In Date</label>
                        <input type="text" id="quick_checkin" name="check_in"
                               class="form-control" readonly
                               placeholder="Select check-in date"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="booking-field">
                        <label for="quick_checkout">Check-Out Date</label>
                        <input type="text" id="quick_checkout" name="check_out"
                               class="form-control" readonly
                               placeholder="Select check-out date"
                               value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="booking-field">
                        <label for="quick_type">Room Type</label>
                        <select id="quick_type" name="room_type" class="form-select">
                            <option value="">Any Type</option>
                            <?php foreach (['Single','Double','Deluxe','Suite','Family','Dormitory'] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="booking-field">
                        <label for="quick_guests">Guests</label>
                        <select id="quick_guests" name="num_guests" class="form-select">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> Guest<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-check-avail">
                        <i class="bi bi-search me-2"></i>Search Available Rooms
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="section-pad" style="background:var(--cream)">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-badge">Why Choose Us</span>
            <h2 class="section-title">The Karavali Experience</h2>
            <div class="section-divider center"></div>
            <p class="section-subtitle mx-auto">Coastal warmth and genuine hospitality, crafted to make your stay extraordinary.</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon'=>'bi-shield-check',  'title'=>'Safe & Secure',       'desc'=>'Round-the-clock security and CCTV surveillance for your peace of mind.'],
                ['icon'=>'bi-wifi',           'title'=>'High-Speed WiFi',     'desc'=>'Complimentary high-speed internet access throughout the property.'],
                ['icon'=>'bi-cup-hot',        'title'=>'Room Service',        'desc'=>'In-room dining available around the clock to satisfy your cravings.'],
                ['icon'=>'bi-geo-alt',        'title'=>'Prime Location',      'desc'=>'Centrally located with easy access to major attractions and transport.'],
                ['icon'=>'bi-star',           'title'=>'Premium Amenities',   'desc'=>'Curated amenities including spa, laundry, and concierge services.'],
                ['icon'=>'bi-headset',        'title'=>'24/7 Support',        'desc'=>'Our dedicated team is always available to assist you anytime.'],
                ['icon'=>'bi-car-front',      'title'=>'Airport Transfers',   'desc'=>'Comfortable pick-up and drop service to the airport on request.'],
                ['icon'=>'bi-credit-card',    'title'=>'Easy Payments',       'desc'=>'Multiple payment options including cards, UPI, and cash.'],
                ['icon'=>'bi-heart',          'title'=>'Genuine Hospitality', 'desc'=>'Warm, personalized service that makes you feel truly at home.'],
            ];
            foreach ($features as $i => $f): ?>
            <div class="col-lg-4 col-md-6" data-animate="fadeInUp" style="animation-delay:<?= $i*0.08 ?>s">
                <div class="feature-card">
                    <div class="feature-icon"><i class="<?= $f['icon'] ?>"></i></div>
                    <h5 class="feature-title"><?= $f['title'] ?></h5>
                    <p class="feature-desc"><?= $f['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Rooms -->
<section class="section-pad" style="background:var(--white)">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
            <div>
                <span class="section-badge">Our Accommodations</span>
                <h2 class="section-title mb-0">Featured Rooms</h2>
                <div class="section-divider"></div>
            </div>
            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-outline-hotel"><i class="bi bi-grid me-2"></i>View All Rooms</a>
        </div>
        <?php if (empty($featuredRooms)): ?>
            <div class="text-center py-5">
                <i class="bi bi-door-open" style="font-size:3rem;color:var(--border)"></i>
                <p class="text-muted mt-3">No rooms available at the moment.</p>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featuredRooms as $i => $room):
                $amenities = array_map('trim', explode(',', $room['amenities'] ?? ''));
                $imgUrl = $roomImages[$room['room_type']] ?? $roomImages['Single'];
            ?>
            <div class="col-lg-4 col-md-6" data-animate="fadeInUp" style="animation-delay:<?= $i*0.1 ?>s">
                <div class="room-card">
                    <div class="room-card-image">
                        <img src="<?= $imgUrl ?>" alt="<?= sanitize($room['room_type']) ?> Room" loading="lazy">
                        <span class="room-type-badge"><?= sanitize($room['room_type']) ?></span>
                        <span class="room-price-badge"><?= formatCurrency($room['price']) ?>/night</span>
                    </div>
                    <div class="room-card-body">
                        <h4 class="room-card-title">Room <?= sanitize($room['room_number']) ?> — <?= sanitize($room['room_type']) ?></h4>
                        <p class="room-card-desc"><?= sanitize($room['description'] ?: 'Comfortable and well-appointed room with all essential amenities for a pleasant stay.') ?></p>
                        <div class="room-amenity-tags">
                            <?php foreach (array_slice($amenities, 0, 4) as $am): ?>
                                <span class="amenity-tag"><i class="<?= getAmenityIcon($am) ?>"></i><?= sanitize($am) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="room-meta">
                            <span class="room-capacity"><i class="bi bi-people"></i> Up to <?= (int)$room['capacity'] ?> guest<?= $room['capacity'] > 1 ? 's' : '' ?></span>
                            <a href="<?= SITE_URL ?>/pages/booking.php?room_id=<?= urlencode($room['id']) ?>" class="btn-book-room">Book Now</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Testimonials -->
<section class="section-pad testimonial-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-badge" style="color:var(--accent-light)">Guest Stories</span>
            <h2 class="section-title" style="color:var(--white)">What Our Guests Say</h2>
            <div class="section-divider center"></div>
        </div>
        <div class="row g-4">
            <?php
            $testimonials = [
                ['name'=>'Rajesh Kumar',  'location'=>'Bangalore',  'rating'=>5, 'text'=>'Absolutely wonderful stay! The staff were incredibly warm and attentive. The room was spotlessly clean and the room service was prompt. Will definitely return.'],
                ['name'=>'Priya Sharma',  'location'=>'Mysore',     'rating'=>5, 'text'=>'The Suite was beyond our expectations. Jacuzzi, lake view, impeccable service — it felt like a home away from home. Our anniversary was made truly special.'],
                ['name'=>'John Smith',    'location'=>'London, UK', 'rating'=>5, 'text'=>'Best budget hotel experience I have had in India. Clean, friendly staff, great WiFi and the breakfast was delicious. The location is very convenient too.'],
            ];
            foreach ($testimonials as $i => $t):
                $initials = implode('', array_map(fn($w) => $w[0], explode(' ', $t['name'])));
            ?>
            <div class="col-lg-4" data-animate="fadeInUp" style="animation-delay:<?= $i*0.15 ?>s">
                <div class="testimonial-card">
                    <div class="testimonial-stars"><?= str_repeat('<i class="bi bi-star-fill"></i> ', $t['rating']) ?></div>
                    <p class="testimonial-text">"<?= $t['text'] ?>"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><?= substr($initials, 0, 2) ?></div>
                        <div>
                            <div class="testimonial-name"><?= $t['name'] ?></div>
                            <div class="testimonial-location"><i class="bi bi-geo-alt me-1"></i><?= $t['location'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section-pad" style="background:var(--accent-pale)">
    <div class="container text-center">
        <span class="section-badge">Start Your Journey</span>
        <h2 class="section-title mb-3">Ready for an Unforgettable Stay?</h2>
        <p class="section-subtitle mx-auto mb-5">Book your room today and experience the finest hospitality in the heart of the city.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?= SITE_URL ?>/pages/booking.php" class="btn-primary-hotel" style="padding:16px 40px;font-size:1rem"><i class="bi bi-calendar-plus me-2"></i>Book Your Room</a>
            <a href="<?= SITE_URL ?>/pages/contact.php" class="btn-outline-hotel" style="padding:15px 36px;font-size:1rem"><i class="bi bi-telephone me-2"></i>Contact Us</a>
        </div>
    </div>
</section>

<!-- Flatpickr JS + initialization -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Initialize Check-Out first so Check-In can reference it
    const coPicker = flatpickr('#quick_checkout', {
        dateFormat:    'Y-m-d',
        minDate:       '<?= date('Y-m-d', strtotime('+1 day')) ?>',
        defaultDate:   '<?= date('Y-m-d', strtotime('+1 day')) ?>',
        disableMobile: true,
        allowInput:    false,
    });

    // Initialize Check-In
    flatpickr('#quick_checkin', {
        dateFormat:    'Y-m-d',
        minDate:       'today',
        defaultDate:   '<?= date('Y-m-d') ?>',
        disableMobile: true,
        allowInput:    false,
        onChange: function(selectedDates) {
            if (selectedDates[0]) {
                const nextDay = new Date(selectedDates[0]);
                nextDay.setDate(nextDay.getDate() + 1);
                // Push check-out minimum forward
                coPicker.set('minDate', nextDay);
                // Auto-advance check-out if it's before check-in
                if (coPicker.selectedDates[0] && coPicker.selectedDates[0] <= selectedDates[0]) {
                    coPicker.setDate(nextDay);
                }
            }
        }
    });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
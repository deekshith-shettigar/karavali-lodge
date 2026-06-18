<?php
$pageTitle = "Our Rooms - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$isLoggedIn = isLoggedIn();

// Filters
$typeFilter  = sanitize($_GET['type']  ?? '');
$priceFilter = sanitize($_GET['price'] ?? '');
$sortOrder   = sanitize($_GET['sort']  ?? 'price_asc');
$checkin     = sanitize($_GET['check_in']  ?? '');
$checkout    = sanitize($_GET['check_out'] ?? '');

// Build query
$where  = ["r.status NOT IN ('Maintenance','Out of Order')"];
$params = [];

if ($typeFilter) {
    $where[]  = "r.room_type = ?";
    $params[] = $typeFilter;
}

if ($priceFilter) {
    [$minP, $maxP] = explode('-', $priceFilter . '-99999');
    $where[]  = "r.price >= ? AND r.price <= ?";
    $params[] = (float)$minP;
    $params[] = (float)$maxP;
}

// Exclude rooms booked for selected dates
if ($checkin && $checkout) {
    $where[] = "r.id NOT IN (
        SELECT room_id FROM bookings
        WHERE room_id IS NOT NULL
        AND status NOT IN ('Cancelled','Completed','Checked-Out')
        AND check_in < ? AND check_out > ?
        AND check_out > CURDATE()
    )";
    $params[] = $checkout;
    $params[] = $checkin;
    $where[] = "r.id NOT IN (
        SELECT room_id FROM online_booking_requests
        WHERE room_id IS NOT NULL
        AND status NOT IN ('Cancelled','Rejected')
        AND check_in < ? AND check_out > ?
        AND check_out > CURDATE()
    )";
    $params[] = $checkout;
    $params[] = $checkin;
}

$orderClause = match($sortOrder) {
    'price_desc' => 'r.price DESC',
    'name_asc'   => 'r.room_number ASC',
    default      => 'r.price ASC',
};

$sql = "SELECT r.* FROM rooms r WHERE " . implode(' AND ', $where) . " ORDER BY $orderClause";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$roomImages = [
    'Single'    => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80',
    'Double'    => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=600&q=80',
    'Deluxe'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&q=80',
    'Suite'     => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=600&q=80',
    'Family'    => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=600&q=80',
    'Dormitory' => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=600&q=80',
];
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">Our Rooms</span>
        </div>
        <h1>Our Rooms & Suites</h1>
        <p>Choose from our curated collection of comfortable accommodations</p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">

        <!-- Filter Bar -->
        <div class="filter-bar-hotel mb-5">
            <form method="GET" class="d-flex align-items-end gap-3 flex-wrap w-100" id="filterForm">
                <?php if ($checkin):  ?><input type="hidden" name="check_in"  value="<?= sanitize($checkin) ?>"><?php endif; ?>
                <?php if ($checkout): ?><input type="hidden" name="check_out" value="<?= sanitize($checkout) ?>"><?php endif; ?>

                <div>
                    <label for="filterType">Room Type</label>
                    <select id="filterType" name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <?php foreach (['Single','Double','Deluxe','Suite','Family','Dormitory'] as $t): ?>
                            <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="filterPrice">Price Range</label>
                    <select id="filterPrice" name="price" class="form-select" onchange="this.form.submit()">
                        <option value="">Any Price</option>
                        <option value="0-1000"    <?= $priceFilter === '0-1000'    ? 'selected' : '' ?>>Under ₹1,000</option>
                        <option value="1000-2000" <?= $priceFilter === '1000-2000' ? 'selected' : '' ?>>₹1,000 – ₹2,000</option>
                        <option value="2000-4000" <?= $priceFilter === '2000-4000' ? 'selected' : '' ?>>₹2,000 – ₹4,000</option>
                        <option value="4000-9999" <?= $priceFilter === '4000-9999' ? 'selected' : '' ?>>Above ₹4,000</option>
                    </select>
                </div>

                <div>
                    <label for="filterSort">Sort By</label>
                    <select id="filterSort" name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="price_asc"  <?= $sortOrder === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sortOrder === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name_asc"   <?= $sortOrder === 'name_asc'   ? 'selected' : '' ?>>Room Number</option>
                    </select>
                </div>

                <?php if ($typeFilter || $priceFilter): ?>
                <div class="ms-auto">
                    <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-outline-hotel" style="padding:10px 20px;font-size:0.85rem">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results count -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0" style="font-size:0.9rem">
                <strong><?= count($rooms) ?></strong> room<?= count($rooms) !== 1 ? 's' : '' ?> found
                <?= $typeFilter ? "· <strong>$typeFilter</strong>" : '' ?>
                <?= ($checkin && $checkout) ? " · Available " . date('d M', strtotime($checkin)) . " – " . date('d M', strtotime($checkout)) : '' ?>
            </p>
            <?php if ($checkin && $checkout): ?>
            <span style="font-size:0.8rem;color:var(--accent);font-weight:600">
                <i class="bi bi-calendar-check me-1"></i>Showing available rooms only
            </span>
            <?php endif; ?>
        </div>

        <!-- Room Grid -->
        <?php if (empty($rooms)): ?>
        <div class="empty-state-hotel">
            <i class="bi bi-door-closed d-block"></i>
            <h5>No Rooms Found</h5>
            <p>Try adjusting your filters or check back later.</p>
            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-primary-hotel">
                <i class="bi bi-arrow-left me-2"></i>View All Rooms
            </a>
        </div>
        <?php else: ?>
        <div class="row g-4" id="roomGrid">
            <?php foreach ($rooms as $i => $room):
                $amenities = array_map('trim', explode(',', $room['amenities'] ?? ''));
                $imgUrl = $roomImages[$room['room_type']] ?? $roomImages['Single'];
            ?>
            <div class="col-lg-4 col-md-6 room-card-wrapper"
                 data-type="<?= sanitize($room['room_type']) ?>"
                 data-price="<?= (float)$room['price'] ?>">
                <div class="room-card h-100">
                    <div class="room-card-image">
                        <img src="<?= $imgUrl ?>" alt="<?= sanitize($room['room_type']) ?> Room" loading="lazy">
                        <span class="room-type-badge"><?= sanitize($room['room_type']) ?></span>
                        <span class="room-price-badge"><?= formatCurrency($room['price']) ?>/night</span>
                    </div>
                    <div class="room-card-body">
                        <h4 class="room-card-title">
                            Room <?= sanitize($room['room_number']) ?>
                            <span style="font-style:italic;font-size:0.9em;font-weight:400">— <?= sanitize($room['room_type']) ?></span>
                        </h4>
                        <p class="room-card-desc">
                            <?= sanitize($room['description'] ?: 'A comfortable and well-appointed room designed for a relaxing stay.') ?>
                        </p>

                        <div class="room-amenity-tags">
                            <?php foreach (array_slice($amenities, 0, 5) as $am): ?>
                                <span class="amenity-tag">
                                    <i class="<?= getAmenityIcon($am) ?>"></i>
                                    <?= sanitize($am) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (count($amenities) > 5): ?>
                                <span class="amenity-tag" style="background:var(--border);color:var(--text-muted)">
                                    +<?= count($amenities) - 5 ?> more
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Room Details -->
                        <div class="d-flex gap-4 mb-16 text-muted" style="font-size:0.82rem;margin-bottom:14px">
                            <span><i class="bi bi-people me-1"></i><?= (int)$room['capacity'] ?> Guests</span>
                            <span><i class="bi bi-building me-1"></i>Floor <?= sanitize($room['floor'] ?? 'G') ?></span>
                        </div>

                        <div class="room-meta">
                            <div>
                                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Per Night</div>
                                <div style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--accent);font-weight:700">
                                    <?= formatCurrency($room['price']) ?>
                                </div>
                            </div>
                            <?php if ($isLoggedIn): ?>
                            <a href="<?= SITE_URL ?>/pages/room-booking.php?room_id=<?= urlencode($room['id']) ?><?= $checkin ? '&check_in='.urlencode($checkin) : '' ?><?= $checkout ? '&check_out='.urlencode($checkout) : '' ?>"
                               class="btn-book-room">
                                <i class="bi bi-calendar-plus me-1"></i>Book
                            </a>
                            <?php else: ?>
                            <button type="button" class="btn-book-room" onclick="showLoginPopup()">
                                <i class="bi bi-calendar-plus me-1"></i>Book
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$isLoggedIn): ?>
<!-- Login Required Popup -->
<div id="loginPopup" style="
     visibility:hidden;opacity:0;
     position:fixed;inset:0;z-index:9999;
     display:flex;align-items:center;justify-content:center;
     background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);
     transition:opacity 0.2s ease,visibility 0.2s ease;">
    <div style="background:#fff;border-radius:20px;padding:48px 40px;
                max-width:440px;width:90%;text-align:center;
                box-shadow:0 24px 64px rgba(0,0,0,0.3);
                transform:scale(0.92);transition:transform 0.2s ease;">
        <style>
            #loginPopup.active { visibility:visible !important; opacity:1 !important; }
            #loginPopup.active > div { transform:scale(1) !important; }
        </style>
        <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png"
             alt="Karavali Lodge"
             style="width:70px;height:70px;object-fit:contain;margin-bottom:16px;">
        <h3 style="font-family:'Playfair Display',serif;color:#3B1A0A;font-size:1.5rem;margin-bottom:8px;">
            Login Required
        </h3>
        <p style="color:#8B7355;font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1rem;margin-bottom:28px;">
            Please login or create an account to book a room at Karavali Lodge.
        </p>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="<?= SITE_URL ?>/pages/login.php"
               style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;
                      padding:14px 24px;border-radius:10px;font-weight:700;font-size:1rem;
                      text-decoration:none;display:block;
                      box-shadow:0 6px 20px rgba(196,148,58,0.35);">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login to My Account
            </a>
            <a href="<?= SITE_URL ?>/pages/register.php"
               style="background:#f8f4ef;color:#3B1A0A;padding:14px 24px;border-radius:10px;
                      font-weight:600;font-size:1rem;text-decoration:none;display:block;
                      border:1.5px solid #E8D9C0;">
                <i class="bi bi-person-plus me-2"></i>Create New Account
            </a>
            <button type="button" onclick="hideLoginPopup()"
                    style="background:none;border:none;color:#8B7355;font-size:0.88rem;
                           cursor:pointer;margin-top:4px;">
                <i class="bi bi-x me-1"></i>Cancel
            </button>
        </div>
    </div>
</div>

<script>
function showLoginPopup() {
    document.getElementById('loginPopup').classList.add('active');
}
function hideLoginPopup() {
    document.getElementById('loginPopup').classList.remove('active');
}
document.addEventListener('DOMContentLoaded', function () {
    var popup = document.getElementById('loginPopup');
    if (popup) {
        popup.addEventListener('click', function (e) {
            if (e.target === this) hideLoginPopup();
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
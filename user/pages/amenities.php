<?php
$pageTitle = "Amenities & Services - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$stmt = $db->query("SELECT * FROM service_menu ORDER BY category, name");
$services = $stmt->fetchAll();

$grouped = [];
foreach ($services as $s) {
    $grouped[$s['category']][] = $s;
}
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">Amenities</span>
        </div>
        <h1>Amenities & Services</h1>
        <p>Everything you need for a perfect stay, at your fingertips</p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">

        <!-- Core Amenities -->
        <div class="text-center mb-5">
            <span class="section-badge">Included With Your Stay</span>
            <h2 class="section-title">Standard Amenities</h2>
            <div class="section-divider center"></div>
            <p class="section-subtitle mx-auto">Every room comes with these thoughtful comforts.</p>
        </div>

        <div class="row g-4 mb-6">
            <?php
            $coreAmenities = [
                ['icon' => 'bi-wifi',               'title' => 'High-Speed WiFi',     'desc' => 'Complimentary high-speed internet access in all rooms and common areas.'],
                ['icon' => 'bi-thermometer-snow',   'title' => 'Air Conditioning',    'desc' => 'Modern climate control for a comfortable stay in all seasons.'],
                ['icon' => 'bi-tv',                 'title' => 'Smart Television',    'desc' => 'Flat-screen TV with 100+ channels in all rooms.'],
                ['icon' => 'bi-droplet',            'title' => 'Hot Water',           'desc' => '24/7 hot water supply in private bathrooms.'],
                ['icon' => 'bi-shield-check',       'title' => 'CCTV Security',       'desc' => 'Round-the-clock surveillance for your safety and peace of mind.'],
                ['icon' => 'bi-person-badge',       'title' => 'Concierge Service',   'desc' => 'Dedicated staff available 24/7 to assist with all your needs.'],
                ['icon' => 'bi-car-front',          'title' => 'Free Parking',        'desc' => 'Complimentary parking space for all registered guests.'],
                ['icon' => 'bi-cup-hot',            'title' => 'Room Service',        'desc' => 'In-room dining service available throughout the day and night.'],
            ];
            foreach ($coreAmenities as $i => $a):
            ?>
            <div class="col-lg-3 col-md-6" data-animate="fadeInUp" style="animation-delay:<?= $i*0.07 ?>s">
                <div class="amenity-card">
                    <div class="amenity-icon-wrap">
                        <i class="<?= $a['icon'] ?>"></i>
                    </div>
                    <h5 style="font-family:'Playfair Display',serif;color:var(--primary);font-size:1rem;margin-bottom:8px"><?= $a['title'] ?></h5>
                    <p style="font-size:0.85rem;color:var(--text-muted);line-height:1.6;margin:0"><?= $a['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Extra Services (from DB) -->
        <?php if (!empty($grouped)): ?>
        <div class="text-center mb-5 mt-5" style="padding-top:20px">
            <span class="section-badge">On-Request Services</span>
            <h2 class="section-title">Extra Services</h2>
            <div class="section-divider center"></div>
            <p class="section-subtitle mx-auto">Enhance your experience with our premium add-ons.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($grouped as $category => $items): ?>
            <div class="col-12">
                <h4 style="font-family:'Playfair Display',serif;color:var(--primary);margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid var(--border)">
                    <span style="width:8px;height:8px;background:var(--accent);border-radius:50%;display:inline-block;margin-right:10px;vertical-align:middle"></span>
                    <?= sanitize($category) ?>
                </h4>
                <div class="row g-3">
                    <?php foreach ($items as $service): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;display:flex;justify-content:space-between;align-items:center;transition:var(--transition);box-shadow:var(--shadow-sm)"
                             onmouseover="this.style.borderColor='var(--accent)';this.style.boxShadow='var(--shadow-md)'"
                             onmouseout="this.style.borderColor='var(--border)';this.style.boxShadow='var(--shadow-sm)'">
                            <div>
                                <div style="font-weight:600;color:var(--primary);font-size:0.9rem;margin-bottom:2px"><?= sanitize($service['name']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($service['category']) ?></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-family:'Playfair Display',serif;color:var(--accent);font-weight:700;font-size:1rem"><?= formatCurrency($service['price']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CTA -->
        <div style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));border-radius:var(--radius-lg);padding:48px 40px;margin-top:64px;text-align:center">
            <h3 style="font-family:'Playfair Display',serif;color:var(--white);font-size:1.8rem;margin-bottom:12px">
                Ready for a Premium Stay?
            </h3>
            <p style="color:rgba(255,255,255,0.65);font-family:'Cormorant Garamond',serif;font-style:italic;font-size:1.1rem;margin-bottom:28px">
                Book now and enjoy all these amenities during your stay.
            </p>
            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-accent-hotel" style="padding:14px 36px;font-size:0.95rem;border-radius:8px">
                <i class="bi bi-calendar-plus me-2"></i>Book Your Stay
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
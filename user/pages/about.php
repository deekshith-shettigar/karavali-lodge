<?php
$pageTitle = "About Us - Karavali Lodge";
$pageDesc  = "Learn about Karavali Lodge, Mangalore — centrally located, affordable, and welcoming to all guests.";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">About Us</span>
        </div>
        <h1>About Us</h1>
        <p>Know more about Karavali Lodge, Mangalore</p>
    </div>
</div>

<!-- About Main Section -->
<section class="section-pad" style="background:#fff;">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left — Text -->
            <div class="col-lg-6">
                <span class="section-badge">Who We Are</span>
                <h2 class="section-title">Welcome to Karavali Lodge, Mangalore</h2>
                <div class="section-divider"></div>

                <p style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--text-body);line-height:1.9;margin-bottom:20px;">
                    In the heart of <strong>Mangalore City</strong> lies Karavali Lodge. Its central location,
                    affordable rates &amp; good facilities make it a gem among hotels. Karavali Lodge offers you
                    a comfortable stay away from home with its clean &amp; cozy rooms.
                </p>

                <p style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--text-body);line-height:1.9;margin-bottom:20px;">
                    Mangalore is a famous educational destination more than ever before. The whole of the coastal belt
                    Mangalore to Manipal is dotted with Medical, Engineering, Dental, Hotel Management &amp; other colleges.
                </p>

                <p style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;color:var(--text-body);line-height:1.9;margin-bottom:28px;">
                    Whether it is parents &amp; students visiting campuses, tourists, wedding parties or business travellers,
                    <strong style="color:var(--primary)">Karavali Lodge suits the taste and budget of all.</strong>
                </p>

                <div style="margin-top:28px;display:flex;gap:14px;flex-wrap:wrap;">
                    <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn-accent-hotel"
                       style="padding:14px 28px;font-size:0.95rem;border-radius:10px;text-decoration:none;">
                        <i class="bi bi-calendar-plus me-2"></i>Book Your Stay
                    </a>
                    <a href="<?= SITE_URL ?>/pages/contact.php"
                       style="padding:14px 28px;font-size:0.95rem;border-radius:10px;text-decoration:none;
                              border:2px solid var(--accent);color:var(--accent);font-weight:600;display:inline-flex;align-items:center;">
                        <i class="bi bi-envelope me-2"></i>Contact Us
                    </a>
                </div>
            </div>

            <!-- Right — Image -->
            <div class="col-lg-6">
                <div style="position:relative;padding-bottom:30px;padding-right:24px;">
                    <div style="border-radius:20px;overflow:hidden;box-shadow:0 24px 64px rgba(59,26,10,0.15);">
                        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&q=80"
                             alt="Karavali Lodge Mangalore"
                             style="width:100%;height:440px;object-fit:cover;">
                    </div>
                    <!-- Floating rating card -->
                    <div style="position:absolute;bottom:0;left:-16px;
                                background:#fff;border-radius:16px;padding:18px 22px;
                                box-shadow:0 12px 40px rgba(59,26,10,0.15);
                                border:1px solid var(--border);">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:44px;height:44px;background:linear-gradient(135deg,#C4943A,#D4AD5E);
                                        border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-star-fill" style="color:#fff;font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:1.3rem;font-weight:700;color:var(--primary);line-height:1;">4.8★</div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">Guest Rating</div>
                            </div>
                        </div>
                    </div>
                    <!-- Logo badge -->
                    <div style="position:absolute;top:-16px;right:8px;
                                width:72px;height:72px;background:#fff;border-radius:50%;
                                display:flex;align-items:center;justify-content:center;
                                box-shadow:0 8px 24px rgba(59,26,10,0.12);
                                border:3px solid #E8D9C0;padding:8px;">
                        <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png" alt="Logo"
                             style="width:100%;height:100%;object-fit:contain;">
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Location Highlights -->
<section class="section-pad" style="background:var(--cream);">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-badge">Our Location</span>
            <h2 class="section-title">Perfectly Located in Mangalore</h2>
            <div class="section-divider center"></div>
            <p style="color:var(--text-muted);max-width:560px;margin:0 auto;font-family:'Cormorant Garamond',serif;font-size:1.05rem;">
                Karavali Lodge is located in the heart of the city with easy access to all major transport hubs.
            </p>
        </div>

        <div class="row g-4">
            <?php
            $locations = [
                ['bi-airplane-fill',   '#C4943A', '20 Minutes',  'From Airport',          'Quick airport transfer on request'],
                ['bi-train-front-fill','#3B1A0A', '5 Minutes',   'From Railway Station',   'Walking distance from city railway'],
                ['bi-bus-front-fill',  '#C4943A', '10 Minutes',  'From Bus Station',       'KSRTC &amp; private buses nearby'],
                ['bi-geo-alt-fill',    '#3B1A0A', 'City Centre', 'Heart of Mangalore',     'All major landmarks within reach'],
            ];
            foreach ($locations as $loc): ?>
            <div class="col-md-6 col-lg-3">
                <div style="background:#fff;border-radius:16px;padding:28px 24px;text-align:center;
                            border:1px solid var(--border);box-shadow:var(--shadow-sm);height:100%;
                            transition:transform 0.2s,box-shadow 0.2s;"
                     onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 32px rgba(59,26,10,0.12)'"
                     onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">
                    <div style="width:60px;height:60px;background:var(--accent-pale);border-radius:16px;
                                display:flex;align-items:center;justify-content:center;
                                margin:0 auto 16px;font-size:1.4rem;color:<?= $loc[1] ?>;">
                        <i class="bi <?= $loc[0] ?>"></i>
                    </div>
                    <div style="font-size:1.4rem;font-weight:700;color:var(--primary);
                                font-family:'Playfair Display',serif;margin-bottom:4px;">
                        <?= $loc[2] ?>
                    </div>
                    <div style="font-weight:600;color:var(--accent);font-size:0.88rem;
                                text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
                        <?= $loc[3] ?>
                    </div>
                    <div style="font-size:0.85rem;color:var(--text-muted);"><?= $loc[4] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Map Section -->
<section style="background:var(--cream);padding-bottom:60px;">
    <div class="container">
        <div style="border-radius:20px;overflow:hidden;box-shadow:0 12px 40px rgba(59,26,10,0.12);border:1px solid var(--border);">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d248872.838281923!2d74.688909825511!3d12.930966197626665!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ba35a4c37bf488f%3A0x827bbc7a74fcfe64!2sMangaluru%2C%20Karnataka!5e0!3m2!1sen!2sin!4v1781414115313!5m2!1sen!2sin"
                width="100%" height="400"
                style="border:0;display:block;"
                allowfullscreen="" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- Who We Welcome -->
<section class="section-pad" style="background:#fff;">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-badge">Our Guests</span>
            <h2 class="section-title">We Welcome Everyone</h2>
            <div class="section-divider center"></div>
        </div>

        <div class="row g-4 justify-content-center">
            <?php
            $guests = [
                ['bi-mortarboard-fill', 'Students &amp; Parents',  'Visiting campuses across Mangalore–Manipal coastal belt'],
                ['bi-briefcase-fill',   'Business Travellers',     'Comfortable stay with all amenities for work trips'],
                ['bi-people-fill',      'Wedding Parties',         'Spacious rooms for families and wedding guests'],
                ['bi-camera-fill',      'Tourists',                'Perfect base to explore Mangalore and coastal Karnataka'],
                ['bi-hospital-fill',    'Medical Visitors',        'Close to hospitals and medical colleges'],
                ['bi-house-heart-fill', 'Families',                'Clean, cozy, and safe rooms for the whole family'],
            ];
            foreach ($guests as $g): ?>
            <div class="col-md-6 col-lg-4">
                <div style="display:flex;align-items:flex-start;gap:16px;background:var(--cream);
                            border-radius:14px;padding:22px;border:1px solid var(--border);">
                    <div style="width:48px;height:48px;background:linear-gradient(135deg,#C4943A,#D4AD5E);
                                border-radius:12px;display:flex;align-items:center;justify-content:center;
                                flex-shrink:0;font-size:1.2rem;color:#fff;">
                        <i class="bi <?= $g[0] ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--primary);margin-bottom:4px;"><?= $g[1] ?></div>
                        <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.5;"><?= $g[2] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section style="background:linear-gradient(135deg,#2A1007,#3B1A0A);padding:60px 0;">
    <div class="container text-center">
        <h2 style="font-family:'Playfair Display',serif;color:#fff;font-size:2rem;margin-bottom:12px;">
            Ready to Experience Karavali Lodge?
        </h2>
        <p style="font-family:'Cormorant Garamond',serif;color:rgba(255,255,255,0.7);
                  font-style:italic;font-size:1.1rem;margin-bottom:32px;">
            K.S. Rao Road, Mangalore · 0824-2389156 · enquiry@karavalilodge.com
        </p>
        <a href="<?= SITE_URL ?>/pages/rooms.php"
           style="background:linear-gradient(135deg,#C4943A,#D4AD5E);color:#2A1007;
                  padding:16px 40px;border-radius:10px;font-weight:700;font-size:1rem;
                  text-decoration:none;display:inline-flex;align-items:center;gap:10px;
                  box-shadow:0 8px 24px rgba(196,148,58,0.4);">
            <i class="bi bi-calendar-plus"></i> Book Your Room Now
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
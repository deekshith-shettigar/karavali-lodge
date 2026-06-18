<?php
require_once __DIR__ . '/config.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$guest = getLoggedInGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? SITE_NAME ?></title>
    <meta name="description" content="<?= $pageDesc ?? 'Karavali Lodge - Premium Lodge & Stay Experience in the heart of the city.' ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/css/style.css" rel="stylesheet">

    <?= $extraHead ?? '' ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">

<!-- Top Info Bar -->
<div class="top-bar">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex gap-4 flex-wrap">
                <span><i class="bi bi-telephone me-1"></i> 0824-2389156 | 0824-4178293</span>
                <span><i class="bi bi-envelope me-1"></i> enquiry@karavalilodge.com</span>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <?php if ($guest): ?>
                    <a href="<?= SITE_URL ?>/pages/my-bookings.php" class="top-bar-link">
                        <i class="bi bi-person-check me-1"></i> Welcome, <?= sanitize($guest['name']) ?>
                    </a>
                    <a href="<?= SITE_URL ?>/pages/logout.php" class="top-bar-link"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/pages/login.php" class="top-bar-link"><i class="bi bi-person me-1"></i> Login</a>
                    <a href="<?= SITE_URL ?>/pages/register.php" class="top-bar-link"><i class="bi bi-person-plus me-1"></i> Register</a>

                <?php endif; ?>
                <div class="d-flex gap-2">
                    <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Navigation -->
<nav class="main-navbar navbar navbar-expand-lg sticky-top" id="mainNav">
    <div class="container d-flex align-items-center justify-content-between flex-nowrap">
        <!-- Logo -->
        <a class="navbar-brand flex-shrink-0" href="<?= SITE_URL ?>/index.php">
            <div class="brand-wrapper">
                <div class="brand-emblem" style="background:none;box-shadow:none;padding:0;width:40px;height:40px;flex-shrink:0;">
                    <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png"
                         alt="Karavali Lodge Logo"
                         style="width:40px;height:40px;object-fit:contain;border-radius:8px;">
                </div>
                <div class="brand-text">
                    <span class="brand-name">Karavali Lodge</span>
                </div>
            </div>
        </a>

        <!-- Mobile toggler — opens right offcanvas -->
        <button class="navbar-toggler flex-shrink-0 ms-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mobileNav"
                aria-controls="mobileNav" aria-label="Open navigation menu">
            <span class="toggler-icon"><i class="bi bi-list"></i></span>
        </button>

        <!-- Desktop nav (hidden on mobile) -->
        <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarMain">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="<?= SITE_URL ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'about' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'rooms' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/rooms.php">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'amenities' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/amenities.php">Amenities</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'gallery' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/gallery.php">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'contact' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/contact.php">Contact</a>
                </li>
                <?php if ($guest): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'my-bookings' ? 'active' : '' ?>" href="<?= SITE_URL ?>/pages/my-bookings.php">
                        <i class="bi bi-calendar-check me-1"></i>My Bookings
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-book" href="<?= SITE_URL ?>/pages/rooms.php">
                        <i class="bi bi-calendar-plus me-2"></i>Book Now
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Right Offcanvas — mobile navigation ──────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">

    <!-- Offcanvas header -->
    <div class="offcanvas-header" style="background:var(--primary);padding:18px 20px;">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= SITE_URL ?>/images/Lodge_Logoo.png" alt="Logo"
                 style="width:36px;height:36px;object-fit:contain;border-radius:6px;">
            <span style="font-family:'Playfair Display',serif;font-style:italic;
                          font-size:1.05rem;font-weight:600;color:#F5E6C8;">
                Karavali Lodge
            </span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Offcanvas body -->
    <div class="offcanvas-body" style="padding:0;background:#fdfaf5;">

        <!-- Nav links -->
        <ul class="list-unstyled mb-0" style="border-bottom:1px solid #f0e8d8;">
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'index' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/index.php">
                    <i class="bi bi-house me-3"></i>Home
                </a>
            </li>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'about' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/about.php">
                    <i class="bi bi-info-circle me-3"></i>About
                </a>
            </li>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'rooms' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/rooms.php">
                    <i class="bi bi-door-open me-3"></i>Rooms
                </a>
            </li>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'amenities' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/amenities.php">
                    <i class="bi bi-stars me-3"></i>Amenities
                </a>
            </li>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'gallery' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/gallery.php">
                    <i class="bi bi-images me-3"></i>Gallery
                </a>
            </li>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'contact' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/contact.php">
                    <i class="bi bi-envelope me-3"></i>Contact
                </a>
            </li>
            <?php if ($guest): ?>
            <li>
                <a class="mobile-nav-link <?= $currentPage === 'my-bookings' ? 'mobile-nav-active' : '' ?>"
                   href="<?= SITE_URL ?>/pages/my-bookings.php">
                    <i class="bi bi-calendar-check me-3"></i>My Bookings
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Book Now CTA -->
        <div style="padding:20px 16px;">
            <a href="<?= SITE_URL ?>/pages/rooms.php" class="btn btn-book w-100"
               style="justify-content:center;font-size:1rem;padding:12px;">
                <i class="bi bi-calendar-plus me-2"></i>Book Now
            </a>
        </div>

        <!-- Auth links -->
        <div style="padding:0 16px 20px;border-top:1px solid #f0e8d8;padding-top:16px;">
            <?php if ($guest): ?>
                <div style="font-size:0.85rem;color:var(--primary);margin-bottom:10px;">
                    <i class="bi bi-person-check me-2"></i>
                    Welcome, <strong><?= sanitize($guest['name']) ?></strong>
                </div>
                <a href="<?= SITE_URL ?>/pages/logout.php"
                   style="font-size:0.85rem;color:#888;text-decoration:none;">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            <?php else: ?>
                <div class="d-flex gap-3">
                    <a href="<?= SITE_URL ?>/pages/login.php"
                       style="font-size:0.85rem;color:var(--accent);text-decoration:none;font-weight:500;">
                        <i class="bi bi-person me-1"></i>Login
                    </a>
                    <a href="<?= SITE_URL ?>/pages/register.php"
                       style="font-size:0.85rem;color:var(--accent);text-decoration:none;font-weight:500;">
                        <i class="bi bi-person-plus me-1"></i>Register
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contact info strip -->
        <div style="margin:0 16px 20px;padding:12px 14px;background:#f5ede0;
                    border-radius:10px;font-size:0.8rem;color:#6b5c45;line-height:1.8;">
            <div><i class="bi bi-telephone me-2" style="color:var(--accent);"></i>0824-2389156</div>
            <div><i class="bi bi-envelope me-2" style="color:var(--accent);"></i>enquiry@karavalilodge.com</div>
        </div>

    </div>
</div>
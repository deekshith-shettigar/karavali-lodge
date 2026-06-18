<!-- Footer -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <!-- Brand Column — full width on mobile, 4 cols on desktop -->
                <div class="col-12 col-lg-4">
                    <div class="footer-brand">
                        <div class="footer-logo">
                            <div class="brand-emblem" style="background:none;box-shadow:none;padding:0;width:48px;height:48px;flex-shrink:0;"><img src="<?= SITE_URL ?>/images/Lodge_Logoo.png" alt="Karavali Lodge Logo" style="width:48px;height:48px;object-fit:contain;border-radius:10px;"></div>
                            <div>
                                <div class="footer-brand-name">Karavali Lodge</div>
                                <div class="footer-brand-sub">Lodge Management · Est. 2010</div>
                            </div>
                        </div>
                        <p class="footer-desc">
                            Experience the warmth of genuine hospitality at Karavali Lodge. 
                            Where every stay becomes a cherished memory.
                        </p>
                        <div class="footer-socials">
                            <a href="#" class="footer-social"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="footer-social"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="footer-social"><i class="bi bi-twitter-x"></i></a>
                            <a href="#" class="footer-social"><i class="bi bi-youtube"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Quick Links — half width on mobile, side by side with Room Types -->
                <div class="col-6 col-md-4 col-lg-2">
                    <h5 class="footer-heading">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?= SITE_URL ?>/index.php">Home</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php">Our Rooms</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/amenities.php">Amenities</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/gallery.php">Gallery</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php">Book Now</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/contact.php">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Room Types — half width on mobile, side by side with Quick Links -->
                <div class="col-6 col-md-4 col-lg-2">
                    <h5 class="footer-heading">Room Types</h5>
                    <ul class="footer-links">
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Single">Single Rooms</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Double">Double Rooms</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Deluxe">Deluxe Rooms</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Suite">Suites</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Family">Family Rooms</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/rooms.php?type=Dormitory">Dormitory</a></li>
                    </ul>
                </div>

                <!-- Contact Info — full width on mobile -->
                <div class="col-12 col-md-4 col-lg-4">
                    <h5 class="footer-heading">Contact Us</h5>
                    <ul class="footer-contact">
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>K.S. Rao Road,<br>Mangalore - 575001</span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <span>0824-2389156<br>0824-4178293</span>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <span>enquiry@karavalilodge.com</span>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill"></i>
                            <span>24/7 Reception<br>Check-in: 12:00 PM | Check-out: 11:00 AM</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-center text-md-start">
                <p class="mb-0" style="color:rgba(255,255,255,0.5);font-size:0.85rem;">&copy; <?= date('Y') ?> Karavali Lodge, Mangalore. All rights reserved.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms & Conditions</a>
                    <a href="#">Cancellation Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top -->
<button class="scroll-top-btn" id="scrollTopBtn" >
    <i class="bi bi-arrow-up"></i>
</button>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= SITE_URL ?>/js/main.js"></script>
<?= $extraFooter ?? '' ?>
</body>
</html>
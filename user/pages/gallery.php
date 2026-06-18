<?php
$pageTitle = "Gallery - Karavali Lodge";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

$gallery = [
    ['url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', 'title' => 'Hotel Facade', 'cat' => 'exterior'],
    ['url' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80', 'title' => 'Single Room', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=800&q=80', 'title' => 'Double Room', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80', 'title' => 'Deluxe Room', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&q=80', 'title' => 'Suite Living Room', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=800&q=80', 'title' => 'Family Room', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?w=800&q=80', 'title' => 'Spa & Wellness', 'cat' => 'amenities'],
    ['url' => 'https://images.unsplash.com/photo-1514190051997-0f6f39ca5cde?w=800&q=80', 'title' => 'Restaurant', 'cat' => 'dining'],
    ['url' => 'https://images.unsplash.com/photo-1551632436-cbf8dd35adfa?w=800&q=80', 'title' => 'Room Service', 'cat' => 'dining'],
    ['url' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80', 'title' => 'Reception', 'cat' => 'exterior'],
    ['url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=800&q=80', 'title' => 'Bathroom Suite', 'cat' => 'rooms'],
    ['url' => 'https://images.unsplash.com/photo-1499955085172-a104c9463ece?w=800&q=80', 'title' => 'Common Area', 'cat' => 'exterior'],
];
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="container page-hero-content">
        <div class="breadcrumb-hotel mb-3">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <span class="sep">›</span>
            <span class="current">Gallery</span>
        </div>
        <h1>Photo Gallery</h1>
        <p>A glimpse into your home away from home</p>
    </div>
</div>

<section class="section-pad" style="background:var(--cream)">
    <div class="container">

        <!-- Filter Tabs -->
        <div class="d-flex gap-2 flex-wrap mb-5 justify-content-center">
            <?php
            $categories = ['all' => 'All Photos', 'rooms' => 'Rooms', 'dining' => 'Dining', 'amenities' => 'Amenities', 'exterior' => 'Exterior'];
            foreach ($categories as $key => $label):
            ?>
            <button class="gallery-filter-btn <?= $key === 'all' ? 'active' : '' ?>"
                    data-filter="<?= $key ?>"
                    style="padding:8px 20px;border-radius:50px;border:1.5px solid var(--border);background:var(--white);color:var(--text-muted);font-size:0.85rem;font-weight:500;cursor:pointer;transition:all 0.3s;font-family:'Jost',sans-serif">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="row g-3" id="galleryGrid">
            <?php foreach ($gallery as $i => $img): ?>
            <div class="col-lg-4 col-md-6 gallery-col" data-cat="<?= $img['cat'] ?>">
                <div class="gallery-item" data-animate="fadeInUp" style="animation-delay:<?= ($i%6)*0.08 ?>s">
                    <img src="<?= $img['url'] ?>" alt="<?= sanitize($img['title']) ?>" loading="lazy">
                    <div class="gallery-overlay">
                        <div style="color:var(--white)">
                            <div style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:600"><?= sanitize($img['title']) ?></div>
                            <div style="font-size:0.75rem;color:var(--accent-light);text-transform:capitalize"><?= $img['cat'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.gallery-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.gallery-filter-btn').forEach(b => {
            b.style.background = 'var(--white)';
            b.style.color = 'var(--text-muted)';
            b.style.borderColor = 'var(--border)';
        });
        this.style.background = 'var(--primary)';
        this.style.color = '#fff';
        this.style.borderColor = 'var(--primary)';

        const filter = this.getAttribute('data-filter');
        document.querySelectorAll('.gallery-col').forEach(col => {
            if (filter === 'all' || col.getAttribute('data-cat') === filter) {
                col.style.display = '';
            } else {
                col.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

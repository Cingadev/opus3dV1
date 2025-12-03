<?php
require_once 'config.php';

session_start();

$conn = getDBConnection();

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
    }
}

// Get featured products (max 3)
$featured_stmt = $conn->prepare("
    SELECT id, name, slug, price, compare_price, image, short_description, created_at, sales_count, is_drop
    FROM products 
    WHERE status = 'active' 
    AND featured = 1 
    ORDER BY created_at DESC 
    LIMIT 3
");
$featured_stmt->execute();
$featured_result = $featured_stmt->get_result();
$featured_products = $featured_result->fetch_all(MYSQLI_ASSOC);
$featured_stmt->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opus3D - Custom 3D Figures</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Countdown Topbar -->
    <div class="topbar-countdown">
        <div class="container countdown-content">
            <span class="countdown-label">Prossimo Drop Esclusivo tra:</span>
            <div id="countdown" class="countdown-timer">
                <div class="time-block">
                    <span id="days">00</span>
                    <small>Giorni</small>
                </div>
                <div class="separator">:</div>
                <div class="time-block">
                    <span id="hours">00</span>
                    <small>Ore</small>
                </div>
                <div class="separator">:</div>
                <div class="time-block">
                    <span id="minutes">00</span>
                    <small>Min</small>
                </div>
                <div class="separator">:</div>
                <div class="time-block">
                    <span id="seconds">00</span>
                    <small>Sec</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Newsletter Modal -->
    <div id="drop-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h3>Non perdere il prossimo Drop!</h3>
                <p>I nostri pezzi unici vanno a ruba velocemente. Iscriviti per avere l'accesso prioritario.</p>
            </div>
            <form class="modal-form">
                <input type="email" placeholder="La tua email..." required>
                <button type="submit" class="btn-primary full-width">Avvisami del Drop</button>
            </form>
            <p class="small-text mute">Prossimo drop stimato: <strong>15 Dicembre</strong></p>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">Opus3D</div>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label">
                <span></span>
                <span></span>
                <span></span>
            </label>
            <ul class="nav-links">
                <li><a href="#">Home</a></li>
                <li><a href="#">Drop</a></li>
                <li><a href="#">Custom</a></li>
            </ul>
            <a href="cart.php" class="btn-secondary nav-btn">Carrello (<span id="nav-cart-count"><?php echo $cart_count; ?></span>)</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container hero-content">
            <div class="hero-text">
                <h1>Il Tuo Mondo in <span class="highlight">3D</span></h1>
                <p>Dai vita alla tua immaginazione con figure personalizzate in stile Funko Pop. Stampa 3D di alta qualità, colori vibranti e design unico.</p>
                <a href="#drop" class="btn-primary">Scopri il Drop</a>
            </div>
            <div class="hero-image">
                <div class="image-wrapper">
                    <img src="assets/images/hero.png" alt="3D Printed Figure Showcase">
                </div>
            </div>
        </div>
    </header>

    <!-- Product Showcase (First 3 Models) -->
    <section id="drop" class="products-section">
        <div class="container">
            <div class="section-header">
                <h2>Il Drop Esclusivo</h2>
                <p>Modelli unici in edizione limitata. Disponibili solo per due mesi.</p>
            </div>
            <div class="products-grid drop-grid">
                <?php if (count($featured_products) > 0): ?>
                    <?php foreach ($featured_products as $index => $product): ?>
                        <?php
                        // Determine badge
                        $badge = '';
                        $days_since_created = floor((time() - strtotime($product['created_at'])) / (60 * 60 * 24));
                        if ($days_since_created <= 30) {
                            $badge = '<span class="badge">Nuovo</span>';
                        } elseif ($product['sales_count'] > 10) {
                            $badge = '<span class="badge">Best Seller</span>';
                        } elseif ($product['is_drop']) {
                            $badge = '<span class="badge">Drop</span>';
                        }
                        
                        // Get product image or placeholder
                        $product_image = !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://placehold.co/400x400/f0f0f0/5FB7BB?text=No+Image';
                        $product_name = htmlspecialchars($product['name']);
                        $product_slug = htmlspecialchars($product['slug']);
                        $product_price = number_format(floatval($product['price']), 2, ',', '.');
                        $product_description = !empty($product['short_description']) ? htmlspecialchars($product['short_description']) : 'Scopri questo prodotto esclusivo.';
                        ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product.php?slug=<?php echo $product_slug; ?>">
                                    <img src="<?php echo $product_image; ?>" alt="<?php echo $product_name; ?>">
                                </a>
                                <?php echo $badge; ?>
                            </div>
                            <div class="product-info">
                                <h3><a href="product.php?slug=<?php echo $product_slug; ?>" style="text-decoration: none; color: inherit;"><?php echo $product_name; ?></a></h3>
                                <p><?php echo $product_description; ?></p>
                                <div style="margin-bottom: 10px;">
                                    <?php if (!empty($product['compare_price'])): ?>
                                        <span style="text-decoration: line-through; color: #999; margin-right: 8px;">€<?php echo number_format(floatval($product['compare_price']), 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                    <strong style="color: var(--primary-color);">€<?php echo $product_price; ?></strong>
                                </div>
                                <a href="product.php?slug=<?php echo $product_slug; ?>" class="btn-mini">Vedi Dettagli</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback if no featured products -->
                    <div class="product-card">
                        <div class="product-image">
                            <img src="https://placehold.co/400x400/f0f0f0/5FB7BB?text=Hero+Pop" alt="Hero Pop">
                            <span class="badge">Nuovo</span>
                        </div>
                        <div class="product-info">
                            <h3>Hero Pop</h3>
                            <p>Il classico eroe in versione super deformed.</p>
                            <button class="btn-mini">Aggiungi alla Wishlist</button>
                        </div>
                    </div>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="https://placehold.co/400x400/f0f0f0/5FB7BB?text=Villain+Pop" alt="Villain Pop">
                        </div>
                        <div class="product-info">
                            <h3>Villain Pop</h3>
                            <p>Dettagli oscuri e finitura premium matte.</p>
                            <button class="btn-mini">Aggiungi alla Wishlist</button>
                        </div>
                    </div>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="https://placehold.co/400x400/f0f0f0/5FB7BB?text=Pet+Pop" alt="Pet Pop">
                            <span class="badge">Best Seller</span>
                        </div>
                        <div class="product-info">
                            <h3>Pet Pop</h3>
                            <p>Trasforma il tuo animale domestico in un'icona.</p>
                            <button class="btn-mini">Aggiungi alla Wishlist</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Promo Section -->
    <section class="promo-section">
        <div class="container promo-container">
            <div class="promo-content">
                <span class="promo-badge">Offerta Lancio</span>
                <h2>Sconto del 20% sul Primo Ordine</h2>
                <p>Usa il codice <strong>OPUS20</strong> al checkout per ottenere uno sconto esclusivo sulla tua prima figure personalizzata.</p>
                <a href="#" class="btn-white">Approfittane Ora</a>
            </div>
            <div class="promo-visual">
                <div class="circle-graphic"></div>
                <img src="https://placehold.co/300x300/ffffff/5FB7BB?text=20%25+OFF" alt="Discount">
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter-section">
        <div class="container newsletter-container">
            <div class="newsletter-content">
                <h2>Rimani Aggiornato</h2>
                <p>Iscriviti per ottenere l'accesso anticipato ai prossimi drop e promozioni esclusive.</p>
                <form class="newsletter-form">
                    <input type="email" placeholder="La tua email..." required>
                    <button type="submit" class="btn-primary">Iscriviti al Drop</button>
                </form>
                <p class="small-text">Non inviamo spam. Puoi disiscriverti in qualsiasi momento.</p>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <div class="logo">Opus3D</div>
                <p>Figure personalizzate che raccontano la tua storia. Design unico, qualità premium.</p>
                <div class="social-links">
                    <a href="#" aria-label="Instagram"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                    <a href="#" aria-label="Facebook"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                    <a href="#" aria-label="Twitter"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Shop</h4>
                <ul>
                    <li><a href="#">Nuovi Arrivi</a></li>
                    <li><a href="#">Best Sellers</a></li>
                    <li><a href="#">Customizza</a></li>
                    <li><a href="#">Gift Cards</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Supporto</h4>
                <ul>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Spedizioni</a></li>
                    <li><a href="#">Resi</a></li>
                    <li><a href="#">Contattaci</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Legale</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Termini & Condizioni</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2023 Opus3D. Tutti i diritti riservati. Made with imagination.</p>
            </div>
        </div>
    </footer>

    <script>
        // Modal Logic
        const modal = document.getElementById('drop-modal');
        const closeModal = document.querySelector('.close-modal');
        const modalForm = document.querySelector('.modal-form');

        // Show modal after 2 seconds
        window.addEventListener('load', () => {
            setTimeout(() => {
                modal.classList.add('show');
            }, 2000);
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            modal.classList.remove('show');
        });

        // Close on click outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });

        // Form Submission
        modalForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = modalForm.querySelector('button');
            const originalText = btn.innerText;
            
            btn.innerText = 'Iscritto!';
            btn.style.backgroundColor = '#4ca0a4';
            
            setTimeout(() => {
                modal.classList.remove('show');
                btn.innerText = originalText;
                modalForm.reset();
            }, 1000);
        });

        // Scroll Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Countdown Timer Logic
        const dropDate = new Date("December 16, 2025 00:00:00").getTime();

        const countdownFunction = setInterval(() => {
            const now = new Date().getTime();
            const distance = dropDate - now;

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("days").innerText = days < 10 ? "0" + days : days;
            document.getElementById("hours").innerText = hours < 10 ? "0" + hours : hours;
            document.getElementById("minutes").innerText = minutes < 10 ? "0" + minutes : minutes;
            document.getElementById("seconds").innerText = seconds < 10 ? "0" + seconds : seconds;

            if (distance < 0) {
                clearInterval(countdownFunction);
                document.getElementById("countdown").innerHTML = "DROP LIVE!";
            }
        }, 1000);
    </script>
</body>
</html>


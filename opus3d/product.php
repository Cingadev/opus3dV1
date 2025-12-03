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

// Get product ID or slug from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$product_slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;

$product = null;
$product_specs = [];
$gallery_images = [];
$related_products = [];

if ($product_id || $product_slug) {
    // Get product from database
    if ($product_id) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $product_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM products WHERE slug = ? AND status = 'active'");
        $stmt->bind_param("s", $product_slug);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if ($product) {
        // Increment views count
        $update_stmt = $conn->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?");
        $update_stmt->bind_param("i", $product['id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Extract specs from gallery field (format: images|SPECS|json)
        $gallery_raw = $product['gallery'] ?? '';
        if (!empty($gallery_raw) && strpos($gallery_raw, '|SPECS|') !== false) {
            $parts = explode('|SPECS|', $gallery_raw);
            $gallery_images_str = $parts[0];
            if (count($parts) > 1) {
                $specs_json = $parts[1];
                $product_specs = json_decode($specs_json, true) ?: [];
            }
        } else {
            $gallery_images_str = $gallery_raw;
        }
        
        // Parse gallery images (comma separated)
        if (!empty($gallery_images_str)) {
            $gallery_images = array_filter(array_map('trim', explode(',', $gallery_images_str)));
        }
        
        // Add main image to gallery if exists and not already in gallery
        if (!empty($product['image']) && !in_array($product['image'], $gallery_images)) {
            array_unshift($gallery_images, $product['image']);
        }
        
        // If no images at all, use placeholder
        if (empty($gallery_images)) {
            $gallery_images = ['https://placehold.co/600x600/f0f0f0/5FB7BB?text=No+Image'];
        }
        
        // Get related products (same category or featured products, excluding current)
        $category_id = $product['category_id'];
        $current_id = $product['id'];
        
        $related_stmt = $conn->prepare("
            SELECT id, name, slug, price, image, featured 
            FROM products 
            WHERE status = 'active' 
            AND id != ? 
            AND (category_id = ? OR featured = 1)
            ORDER BY featured DESC, created_at DESC 
            LIMIT 3
        ");
        $related_stmt->bind_param("ii", $current_id, $category_id);
        $related_stmt->execute();
        $related_result = $related_stmt->get_result();
        $related_products = $related_result->fetch_all(MYSQLI_ASSOC);
        $related_stmt->close();
    }
}

// If product not found, redirect or show error
if (!$product) {
    header('HTTP/1.0 404 Not Found');
    // You can redirect to 404 page or show error message
    die('Prodotto non trovato');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Opus3D</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="product.css">
</head>
<body>

    <!-- Countdown Topbar -->
    <div class="topbar-countdown">
        <div class="container countdown-content">
            <span class="countdown-label">Prossimo Drop Esclusivo tra:</span>
            <div id="countdown" class="countdown-timer">
                <div class="time-block"><span id="days">00</span><small>Giorni</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="hours">00</span><small>Ore</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="minutes">00</span><small>Min</small></div>
                <div class="separator">:</div>
                <div class="time-block"><span id="seconds">00</span><small>Sec</small></div>
            </div>
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
                <li><a href="index.html">Home</a></li>
                <li><a href="index.html#drop">Drop</a></li>
                <li><a href="#">Custom</a></li>
            </ul>
            <a href="cart.php" class="btn-secondary nav-btn">Carrello (<span id="cart-count"><?php echo $cart_count; ?></span>)</a>
        </div>
    </nav>

    <!-- Product Detail Section -->
    <section class="product-detail-section">
        <div class="container">
            <div class="breadcrumbs">
                <a href="index.html">Home</a> / <a href="index.html#drop">Drop</a> / <span id="breadcrumb-current"><?php echo htmlspecialchars($product['name']); ?></span>
            </div>

            <div class="product-container" id="product-container">
                <!-- Content will be rendered by JavaScript -->
            </div>
        </div>
    </section>

    <!-- Cross Selling Section -->
    <section class="related-products-section">
        <div class="container">
            <div class="section-header">
                <h2>Potrebbero Piacerti Anche</h2>
                <p>Completa la tua collezione con questi modelli.</p>
            </div>
            <div class="products-grid" id="related-products-grid">
                <!-- Content loaded via JS -->
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
        // Countdown Timer Logic (Shared)
        const dropDate = new Date("December 16, 2025 00:00:00").getTime();
        setInterval(() => {
            const now = new Date().getTime();
            const distance = dropDate - now;
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const elDays = document.getElementById("days");
            if(elDays) {
                elDays.innerText = days < 10 ? "0" + days : days;
                document.getElementById("hours").innerText = hours < 10 ? "0" + hours : hours;
                document.getElementById("minutes").innerText = minutes < 10 ? "0" + minutes : minutes;
                document.getElementById("seconds").innerText = seconds < 10 ? "0" + seconds : seconds;
            }
        }, 1000);

        // Product Data from PHP
        const productData = <?php 
            echo json_encode([
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'compare_price' => $product['compare_price'] ? floatval($product['compare_price']) : null,
                'description' => $product['description'],
                'stock' => intval($product['stock_quantity']),
                'stock_status' => $product['stock_status'],
                'images' => !empty($gallery_images) ? $gallery_images : ($product['image'] ? [$product['image']] : []),
                'specs' => $product_specs,
                'slug' => $product['slug']
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?>;

        const relatedProducts = <?php 
            $related_data = [];
            foreach ($related_products as $rel) {
                $related_data[] = [
                    'id' => $rel['id'],
                    'name' => $rel['name'],
                    'slug' => $rel['slug'],
                    'price' => floatval($rel['price']),
                    'image' => $rel['image'] ?: 'https://placehold.co/400x400/f0f0f0/5FB7BB?text=No+Image',
                    'tag' => $rel['featured'] ? 'In Evidenza' : null
                ];
            }
            echo json_encode($related_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?>;

        // Render Product
        function renderProduct() {
            const container = document.getElementById('product-container');
            const breadcrumbName = document.getElementById('breadcrumb-current');
            
            breadcrumbName.innerText = productData.name;
            document.title = `${productData.name} - Opus3D`;

            // Stock status logic
            let stockHtml = '';
            if (productData.stock < 5) {
                stockHtml = `<span class="stock-indicator low">Ultimi ${productData.stock} pezzi!</span>`;
            } else {
                stockHtml = `<span class="stock-indicator">Disponibilità limitata: ${productData.stock} rimasti</span>`;
            }

            // Specs HTML
            let specsHtml = '<div class="specs-list">';
            for (const [key, value] of Object.entries(productData.specs)) {
                specsHtml += `
                    <div class="spec-item">
                        <span class="spec-label">${key}</span>
                        <span>${value}</span>
                    </div>
                `;
            }
            specsHtml += '</div>';

            // Thumbnails HTML
            let thumbnailsHtml = '';
            if (productData.images && productData.images.length > 0) {
                productData.images.forEach((img, index) => {
                    thumbnailsHtml += `<img src="${img}" class="thumbnail ${index === 0 ? 'active' : ''}" onclick="changeImage('${img}', this)" alt="Thumbnail">`;
                });
            }

            const html = `
                <div class="gallery-wrapper">
                    <div class="main-image">
                        <button class="gallery-nav prev" onclick="changeImageByIndex(-1)" id="prev-btn" ${productData.images && productData.images.length <= 1 ? 'style="display:none"' : ''}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <img id="main-product-img" src="${productData.images && productData.images.length > 0 ? productData.images[0] : 'https://placehold.co/600x600/f0f0f0/5FB7BB?text=No+Image'}" alt="${productData.name}">
                        <button class="gallery-nav next" onclick="changeImageByIndex(1)" id="next-btn" ${productData.images && productData.images.length <= 1 ? 'style="display:none"' : ''}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                    ${productData.images && productData.images.length > 1 ? `<div class="thumbnail-row">${thumbnailsHtml}</div>` : ''}
                </div>
                <div class="product-details">
                    <h1>${productData.name}</h1>
                    ${stockHtml}
                    <div class="price">
                        ${productData.compare_price ? `<span class="compare-price">€${productData.compare_price.toFixed(2)}</span>` : ''}
                        €${productData.price.toFixed(2)}
                    </div>
                    <p class="description">${productData.description}</p>
                    
                    ${specsHtml}

                    <div class="product-actions">
                        <div class="quantity-selector">
                            <button class="quantity-btn" onclick="updateQuantity(-1)" id="qty-minus" disabled>−</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="${productData.stock}" readonly>
                            <button class="quantity-btn" onclick="updateQuantity(1)" id="qty-plus" ${productData.stock <= 1 ? 'disabled' : ''}>+</button>
                        </div>
                        <button class="btn-primary full-width" onclick="addToCart()">Aggiungi al Carrello</button>
                    </div>

                    <div class="product-extras">
                        <div class="extra-item">
                            <svg class="extra-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            <div class="extra-text">
                                <h4>Spedizione Rapida</h4>
                                <p>Consegna in 2-4 giorni lavorativi</p>
                            </div>
                        </div>
                        <div class="extra-item">
                            <svg class="extra-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <div class="extra-text">
                                <h4>Pagamenti Sicuri</h4>
                                <p>SSL 100% Sicuro (Visa, MC, PayPal)</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            container.innerHTML = html;
            
            // Initialize image slider
            if (productData.images && productData.images.length > 0) {
                currentImageIndex = 0;
                updateNavButtons();
            }
            
            // Initialize quantity selector buttons
            const qtyInput = document.getElementById('quantity');
            if (qtyInput) {
                const minusBtn = document.getElementById('qty-minus');
                const plusBtn = document.getElementById('qty-plus');
                if (minusBtn) minusBtn.disabled = (parseInt(qtyInput.value) <= 1);
                if (plusBtn) plusBtn.disabled = (parseInt(qtyInput.value) >= productData.stock);
            }
        }

        // Render Related Products
        function renderRelated() {
            const grid = document.getElementById('related-products-grid');
            let html = '';
            
            relatedProducts.forEach(prod => {
                html += `
                    <div class="product-card">
                        <div class="product-image">
                            <img src="${prod.image}" alt="${prod.name}">
                            ${prod.tag ? `<span class="badge">${prod.tag}</span>` : ''}
                        </div>
                        <div class="product-info">
                            <h3>${prod.name}</h3>
                            <p class="price-small">€${prod.price.toFixed(2)}</p>
                            <a href="product.php?slug=${prod.slug}" class="btn-mini">Vedi Dettagli</a>
                        </div>
                    </div>
                `;
            });
            
            grid.innerHTML = html;
        }

        // Image slider state
        let currentImageIndex = 0;

        // Interactivity Functions
        function changeImage(src, thumb) {
            document.getElementById('main-product-img').src = src;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            if (thumb) thumb.classList.add('active');
            
            // Update current index
            if (productData.images && productData.images.length > 0) {
                currentImageIndex = productData.images.indexOf(src);
                updateNavButtons();
            }
        }

        function changeImageByIndex(direction) {
            if (!productData.images || productData.images.length === 0) return;
            
            currentImageIndex += direction;
            
            if (currentImageIndex < 0) {
                currentImageIndex = productData.images.length - 1;
            } else if (currentImageIndex >= productData.images.length) {
                currentImageIndex = 0;
            }
            
            const newSrc = productData.images[currentImageIndex];
            document.getElementById('main-product-img').src = newSrc;
            
            // Update thumbnail active state
            document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
                if (index === currentImageIndex) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
            
            updateNavButtons();
        }

        function updateNavButtons() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            
            if (prevBtn && nextBtn && productData.images && productData.images.length > 1) {
                // Buttons are always enabled for circular navigation
                prevBtn.disabled = false;
                nextBtn.disabled = false;
            }
        }

        function updateQuantity(change) {
            const input = document.getElementById('quantity');
            let newVal = parseInt(input.value) + change;
            if (newVal >= 1 && newVal <= productData.stock) {
                input.value = newVal;
            }
            
            // Update button states
            const minusBtn = input.previousElementSibling;
            const plusBtn = input.nextElementSibling;
            
            if (minusBtn && plusBtn) {
                minusBtn.disabled = (newVal <= 1);
                plusBtn.disabled = (newVal >= productData.stock);
            }
        }

        function addToCart() {
            const qty = parseInt(document.getElementById('quantity').value);
            const btn = document.querySelector('.product-actions .btn-primary');
            const originalText = btn.innerText;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerText = 'Aggiunta in corso...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('product_id', productData.id);
            formData.append('quantity', qty);
            
            // Send request
            fetch('add-to-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerText = 'Aggiunto!';
                    btn.style.backgroundColor = '#4ca0a4';
                    
                    // Update cart count in navbar if exists
                    const cartCount = document.getElementById('cart-count');
                    if (cartCount) {
                        cartCount.innerText = data.cart_count || 0;
                    }
                    
                    // Redirect to cart after 1 second
                    setTimeout(() => {
                        window.location.href = 'cart.php';
                    }, 1000);
                } else {
                    alert(data.error || 'Errore durante l\'aggiunta al carrello');
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Errore di connessione. Riprova.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }

        // Initialize
        window.addEventListener('load', () => {
            renderProduct();
            renderRelated();
        });
    </script>
</body>
</html>

